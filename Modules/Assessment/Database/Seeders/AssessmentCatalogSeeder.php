<?php


namespace Modules\Assessment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assessment\App\Models\AssessmentCategory;
use Modules\Assessment\App\Models\AssessmentGeneralRisk;
use Modules\Assessment\App\Models\AssessmentMethod;

class AssessmentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // ── مناظر شایستگی ──
        $categories = [
            'توانمندی‌های بنیادی ادراکی',
            'توانمندی‌های بنیادی جسمی',
            'شایستگی‌های روانشناختی',
            'وظایف و مسئولیت‌ها',
            'دانش فنی و تخصصی',
        ];
        foreach ($categories as $i => $title) {
            AssessmentCategory::firstOrCreate(['title' => $title], ['sort_order' => $i + 1]);
        }

        // ── ریسک‌های عمومی کاستی شایستگی (۲۱ مورد) ──
        $risks = [
            'هدر رفت منابع', 'کمبود اطلاعات', 'خطا در مکاتبات',
            'تفسیر نادرست قوانین و الزامات', 'خطا در برنامه‌ریزی', 'خطا در اجرای کار',
            'عدم تحویل کار در مهلت مقرر', 'عدم تحویل کار با کیفیت مقرر',
            'عدم تحویل کار با هزینه مقرر', 'ارائه گزارش‌های نادرست',
            'استفاده نادرست از ابزارها و تجهیزات', 'فساد اداری', 'تنش با همکاران',
            'عدم تحقق اهداف تعریف شده', 'عدم به‌کارگیری درست سیستم‌ها و رویکردها',
            'عدم به‌کارگیری خلاقیت و نوآوری', 'توقف در عملیات',
            'عدم برآورده‌سازی نیاز ذینفعان', 'عدم انطباق در محصول',
            'رخ دادن حوادث ایمنی و بهداشت', 'آلایندگی محیط زیستی',
        ];
        foreach ($risks as $title) {
            AssessmentGeneralRisk::firstOrCreate(['title' => $title]);
        }

        // ── روش‌های رفع خلا شایستگی (نمونه — لیست کامل ۴۰تایی را بفرستید) ──
        $methods = [
            'مطالعه کتاب', 'شبیه‌سازی', 'شرکت در وبینار', 'دوره آموزشی حضوری',
            'دوره آموزشی الکترونیکی', 'مربی‌گری (Coaching)', 'منتورینگ',
            'آموزش حین کار', 'چرخش شغلی', 'کارگاه عملی', 'مطالعه موردی (Case Study)',
            'ایفای نقش', 'سایه‌سازی شغلی (Job Shadowing)', 'خودآموزی',
            'واگذاری پروژه چالشی', 'بازدید فنی',
        ];
        foreach ($methods as $title) {
            AssessmentMethod::firstOrCreate(['title' => $title]);
        }
    }
}
