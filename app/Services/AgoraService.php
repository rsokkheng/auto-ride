<?php

namespace App\Services;

class AgoraService
{
    private string $appId;
    private string $certificate;
    private int    $ttl;

    public function __construct()
    {
        $this->appId       = config('services.agora.app_id', '');
        $this->certificate = config('services.agora.certificate', '');
        $this->ttl         = (int) config('services.agora.ttl', 3600);
    }

    public function rtcToken(string $channel, int $uid = 0, int $role = 1): string
    {
        $expireTs = time() + $this->ttl;

        $message = $this->packMessage($this->appId, $channel, $uid, $role, $expireTs);
        $sig     = $this->sign($message);

        return base64_encode(zlib_encode('006' . $this->appId . $expireTs . $sig . $message, ZLIB_ENCODING_DEFLATE));
    }

    private function packMessage(string $appId, string $channel, int $uid, int $role, int $expireTs): string
    {
        $salt     = random_int(1, 0x7FFFFFFF);
        $ts       = time();
        $messages = [
            1 => pack('N', $role),
            2 => $channel,
            3 => (string) $uid,
            4 => pack('N', $expireTs),
        ];

        $content = pack('vNN', 1, $salt, $ts);
        foreach ($messages as $k => $v) {
            $content .= pack('v', $k) . pack('v', strlen($v)) . $v;
        }

        return $appId . pack('N', $expireTs) . $content;
    }

    private function sign(string $message): string
    {
        return hash_hmac('sha256', $message, $this->certificate, true);
    }
}
