<?php

namespace Modules\Assessment\App\Services;

use Modules\Assessment\App\Models\AssessmentCategory;
use Modules\Assessment\App\Models\AssessmentPost;
use Modules\Assessment\App\Models\AssessmentQuestion;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelProfileImporter
{
    private const KNOWN_GRADES = [
        'سرپرست/کارشناس ارشد', 'کارشناس ارشد', 'سرپرست', 'کارشناس', 'رئیس', 'مدیر',
    ];

    private const SKIP_SHEETS = ['ref', 'sheet1', 'راهنما', 'guide'];

    private const CATEGORY_SYNONYMS = [
        'فرهنگ سازی'                                  => 'فرهنگ سازمانی',
        'مدیریت عملکرد افراد، فرآیندها و کمیته ها'    => 'فرآیندها و کمیته ها',
        'مدیریت عملکرد افراد, فرآیندها و کمیته ها'    => 'فرآیندها و کمیته ها',
        'تغییرات'                                     => 'مدیریت تغییر و انعطاف پذیری',
        'ملاحظات محیط زیستی'                          => 'ملاحظات محیط زیستی و مدیریت انرژی',
        'ملاحظات مدیریت انرژی'                        => 'ملاحظات محیط زیستی و مدیریت انرژی',
    ];

    private array $anomalies = [];
    private array $stats = [
        'files' => 0, 'sheets' => 0, 'questions' => 0,
        'created' => 0, 'updated' => 0,
    ];

    public function importFile(string $path, bool $dryRun = false): array
    {
        $this->stats['files']++;
        $fileName = pathinfo($path, PATHINFO_FILENAME);
        $domain   = $this->parseDomain($fileName);

        $spreadsheet = IOFactory::load($path);

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $sheetName = trim($worksheet->getTitle());

            if (in_array(mb_strtolower($sheetName), self::SKIP_SHEETS)) continue;

            $rows  = array_map('array_values', $worksheet->toArray(null, true, true, true));
            $grade = $this->detectGrade($rows);

            if (!$grade) {
                $this->anomalies[] = "فایل {$fileName} / شیت {$sheetName}: رده شغلی پیدا نشد";
                continue;
            }

            $this->stats['sheets']++;
            $this->importSheet($rows, $grade, $sheetName, $domain, $fileName, $dryRun);
        }

        return ['stats' => $this->stats, 'anomalies' => $this->anomalies];
    }

    private function parseDomain(string $fileName): string
    {
        $parts = explode('-', $fileName);
        return $this->normalizeText($parts[1] ?? $parts[0]);
    }

    private function detectGrade(array $rows): ?string
    {
        foreach (array_slice($rows, 0, 10) as $row) {
            foreach ($row as $cell) {
                $value = $this->normalizeText((string) $cell);
                if (in_array($value, self::KNOWN_GRADES)) return $value;
            }
        }
        return null;
    }

    private function importSheet(array $rows, string $grade, string $unit, string $domain, string $fileName, bool $dryRun): void
    {
        $title = str_starts_with($unit, $grade) ? $unit : $grade . ' ' . $unit;

        $post = null;
        if (!$dryRun) {
            $post = AssessmentPost::updateOrCreate(
                ['grade' => $grade, 'unit' => $unit, 'domain' => $domain],
                ['title' => $title, 'is_active' => true]
            );
        }

        $currentCategory = null;

        foreach ($rows as $row) {
            $rowNum   = trim((string) ($row[0] ?? ''));
            $catRaw   = $this->normalizeText((string) ($row[1] ?? ''));
            $titleRaw = $this->cleanTitle((string) ($row[2] ?? ''));
            $scoreRaw = trim((string) ($row[3] ?? ''));

            $scoreIsNumeric  = is_numeric($scoreRaw) && (int) $scoreRaw >= 1 && (int) $scoreRaw <= 5;
            $rowNumIsNumeric = is_numeric($rowNum);

            // ✅ فیلتر ساختاری: ردیف داده باید عنوان معتبر + (امتیاز عددی یا ردیف عددی) داشته باشد
            //    ردیف‌های هدر هیچ‌کدام را ندارند → حذف می‌شوند (حتی با انکودینگ خراب)
            if ($titleRaw === '' || $this->isBlacklisted($titleRaw)) continue;
            if (!$scoreIsNumeric && !$rowNumIsNumeric) continue;

            // به‌روزرسانی منظر جاری (Forward-fill) — فقط برای ردیف‌های داده
            if ($catRaw !== '' && !$this->isBlacklisted($catRaw)) {
                $currentCategory = $this->canonicalCategory($catRaw);
            }

            $score = $scoreIsNumeric ? (int) $scoreRaw : null;
            if ($score === null) {
                $this->anomalies[] = "فایل {$fileName} / {$unit}: امتیاز نامعتبر «{$scoreRaw}» برای «{$titleRaw}»";
            }

            $this->stats['questions']++;
            if ($dryRun) continue;

            try {
                $category = AssessmentCategory::firstOrCreate(
                    ['title' => $currentCategory ?? 'سایر'],
                    ['sort_order' => 99]
                );

                $exists = AssessmentQuestion::where('post_id', $post->id)
                    ->where('category_id', $category->id)
                    ->where('title', $titleRaw)
                    ->exists();

                AssessmentQuestion::updateOrCreate(
                    ['post_id' => $post->id, 'category_id' => $category->id, 'title' => $titleRaw],
                    ['required_score' => $score, 'risk_level' => null, 'fix_deadline' => null, 'is_active' => true]
                );

                $exists ? $this->stats['updated']++ : $this->stats['created']++;
            } catch (\Throwable $e) {
                // ❌ ردیف خراب → لاگ و ادامه
                $this->anomalies[] = "فایل {$fileName} / {$unit}: ذخیره نشد «{$titleRaw}» — " . $e->getMessage();
                $this->stats['skipped'] = ($this->stats['skipped'] ?? 0) + 1;
                continue;
            }
        }
    }

    // ── نرمال‌سازی و پاک‌سازی ──
    private function sanitizeUtf8(string $text): string
    {
        if ($text === '') return '';

        $result = '';
        $len = strlen($text);
        $i = 0;

        while ($i < $len) {
            $byte = ord($text[$i]);

            if ($byte < 0x80) {
                $seqLen = 1;
            } elseif (($byte & 0xE0) === 0xC0) {
                $seqLen = 2;
            } elseif (($byte & 0xF0) === 0xE0) {
                $seqLen = 3;
            } elseif (($byte & 0xF8) === 0xF0) {
                $seqLen = 4;
            } else {
                $i++;          // بایت یتیم (مثل 0xAA تنها) → حذف
                continue;
            }

            $valid = ($i + $seqLen) <= $len;
            if ($valid) {
                for ($j = 1; $j < $seqLen; $j++) {
                    if ((ord($text[$i + $j]) & 0xC0) !== 0x80) {
                        $valid = false;
                        break;
                    }
                }
            }

            if ($valid) {
                $result .= substr($text, $i, $seqLen);
                $i += $seqLen;
            } else {
                $i++;          // توالی ناقص → بایت خراب حذف می‌شود
            }
        }

        return $result;
    }

    private function normalizeText(string $text): string
    {
        $text = $this->sanitizeUtf8($text);
        $text = str_replace(['‌', '‏', '‎'], '', $text);   // حذف نیم‌فاصله و کنترل‌ها
        $text = preg_replace('/\s+/u', ' ', trim($text));
        return $text;
    }

    private function cleanTitle(string $text): string
    {
        $text = $this->normalizeText($text);
        $text = preg_replace('/^شایستگی:/u', '', $text);
        return trim($text, ' .،,:؛;');
    }

    private function canonicalCategory(string $cat): string
    {
        return self::CATEGORY_SYNONYMS[$cat] ?? $cat;
    }

    private function isBlacklisted(string $value): bool
    {
        foreach (['ردیف', 'منظر شایستگی', 'عناوین شایستگی', 'بخش شناسایی', 'رده شغلی', 'امتیاز دهی'] as $kw) {
            if (str_contains($value, $kw)) return true;
        }
        return false;
    }
}
