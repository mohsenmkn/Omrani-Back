<?php

namespace Modules\Library\App\Services\Sms;

use Modules\Library\App\Models\Notification;
use Modules\Library\App\Models\Reservation;
use Carbon\Carbon;

class SmsManager
{
    private MsgwayService $smsService;

    public function __construct(MsgwayService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function sendDueReminder(Reservation $reservation): bool
    {
        $user = $reservation->user;
        $bookTitle = $reservation->bookCopy->book->title;
        $dueDate = $reservation->expected_return_date->format('Y/m/d');

        $messageText = "یادآوری: ۳ روز تا سررسید کتاب «{$bookTitle}» باقی مانده ({$dueDate}). لطفاً نسبت به بازگرداندن اقدام کنید. - کتابخانه گهرترابر";

        return $this->sendAndLog($user, $reservation, 'reminder', $messageText);
    }


    private function sendAndLog($user, $reservation, string $type, string $messageText): bool
    {
        // جلوگیری از ارسال تکراری
        $alreadySent = Notification::where('reservation_id', $reservation->id)
            ->where('type', $type)
            ->whereNotNull('sent_at')
            ->exists();

        if ($alreadySent) {
            return false;
        }

        // ارسال پیامک
        $result = $this->smsService->send($user->mobile, $messageText);

        // ثبت در دیتابیس
        Notification::create([
            'user_id' => $user->id,
            'reservation_id' => $reservation->id,
            'type' => $type,
            'message' => $messageText, // متن کامل ذخیره می‌شود
            'mobile' => $user->mobile,
            'sent_at' => $result['success'] ? Carbon::now() : null,
            'is_read' => false,
        ]);

        return $result['success'];
    }

    /**
     * ارسال پیامک ثبت اولیه رزرو (در انتظار تایید)
     */
    public function sendPendingNotification(Reservation $reservation): bool
    {
        $user = $reservation->user;
        $bookTitle = $reservation->bookCopy->book->title;
        $pickupDate = $reservation->expected_pickup_date->format('Y/m/d');

        $messageText = "کاربر گرامی {$user->name}، درخواست رزرو کتاب «{$bookTitle}» با موفقیت ثبت شد و در انتظار تایید مدیر کتابخانه است. پس از تایید، در تاریخ {$pickupDate} جهت تحویل مراجعه فرمایید. - کتابخانه گهرترابر";

        return $this->sendAndLog($user, $reservation, 'pending', $messageText);
    }

    /**
     * ارسال پیامک تایید رزرو توسط مدیر
     */
    public function sendApprovalNotification(Reservation $reservation): bool
    {
        $user = $reservation->user;
        $bookTitle = $reservation->bookCopy->book->title;
        $pickupDate = $reservation->expected_pickup_date->format('Y/m/d');
        $dueDate = $reservation->expected_return_date->format('Y/m/d');

        $messageText = "رزرو کتاب «{$bookTitle}» تایید شد. لطفاً در تاریخ {$pickupDate} جهت تحویل به کتابخانه مراجعه فرمایید. مهلت بازگشت: {$dueDate}. - کتابخانه گهرترابر";

        return $this->sendAndLog($user, $reservation, 'approval', $messageText);
    }
}
