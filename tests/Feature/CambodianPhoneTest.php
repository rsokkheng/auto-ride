<?php

namespace Tests\Feature;

use App\Models\PhoneOtp;
use App\Rules\CambodianPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Only real Cambodian mobile numbers get an OTP SMS (each one costs money). */
class CambodianPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_number_formats(): void
    {
        foreach (['012345678', '+85512345678', '85512345678', '12345678', '012 345 678', '0971234567', '0881234567'] as $ok) {
            $this->assertTrue(CambodianPhone::isValid($ok), "$ok should be valid");
        }
        foreach (['12345', '0123456789', '097123456', '020123456', '0991234', '0000000000', 'abcdefghi', ''] as $bad) {
            $this->assertFalse(CambodianPhone::isValid($bad), "$bad should be invalid");
        }
    }

    public function test_fake_number_gets_no_otp(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '020000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->assertSame(0, PhoneOtp::count(), 'no OTP generated / SMS sent');
    }

    public function test_real_number_gets_an_otp(): void
    {
        config(['app.debug' => true, 'sms.driver' => 'log']);
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '012345678'])->assertSuccessful();
        $this->assertSame(1, PhoneOtp::count());
    }
}
