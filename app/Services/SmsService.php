<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $to, string $message): bool
    {
        $driver = config('sms.driver', 'log');

        return match ($driver) {
            'twilio'   => $this->sendTwilio($to, $message),
            'nexmo'    => $this->sendNexmo($to, $message),
            'http'     => $this->sendHttp($to, $message),
            'plasgate' => $this->sendPlasgate($to, $message),
            default    => $this->sendLog($to, $message),
        };
    }

    private function sendLog(string $to, string $message): bool
    {
        Log::info('[SMS] To: ' . $to . ' | ' . $message);
        return true;
    }

    private function sendTwilio(string $to, string $message): bool
    {
        $sid   = config('sms.twilio.sid');
        $token = config('sms.twilio.token');
        $from  = config('sms.twilio.from');

        $res = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To'   => $to,
                'Body' => $message,
            ]);

        return $res->successful();
    }

    private function sendNexmo(string $to, string $message): bool
    {
        $res = Http::post('https://rest.nexmo.com/sms/json', [
            'api_key'    => config('sms.nexmo.key'),
            'api_secret' => config('sms.nexmo.secret'),
            'from'       => config('sms.nexmo.from'),
            'to'         => ltrim($to, '+'),
            'text'       => $message,
        ]);

        return $res->successful() && ($res->json('messages.0.status') === '0');
    }

    private function sendHttp(string $to, string $message): bool
    {
        $res = Http::withHeaders(['Authorization' => 'Bearer ' . config('sms.http.api_key')])
            ->post(config('sms.http.url'), [
                'from'    => config('sms.http.from'),
                'to'      => $to,
                'message' => $message,
            ]);

        return $res->successful();
    }

    private function sendPlasgate(string $to, string $message): bool
    {
        $privateKey = config('services.plasgate.private_key');
        $sender     = config('services.plasgate.sender', 'ROTEH');
        $url        = config('services.plasgate.url', 'https://cloudapi.plasgate.com/rest/send');

        $res = Http::withToken($privateKey)
            ->post($url, [
                'sender'  => $sender,
                'to'      => $to,
                'content' => $message,
            ]);

        if (! $res->successful()) {
            Log::error('[Plasgate] SMS failed', [
                'to'     => $to,
                'status' => $res->status(),
                'body'   => $res->body(),
            ]);
            return false;
        }

        return true;
    }
}
