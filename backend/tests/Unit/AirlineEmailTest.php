<?php

namespace Tests\Unit;

use App\Rules\AirlineEmail;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AirlineEmailTest extends TestCase
{
    public static function emails(): array
    {
        return [
            ['pilot@united.com', true],
            ['fa@delta.com', true],
            ['crew@AA.com', true],
            ['ops@allegiant.com', true],
            ['gate@suncountry.com', true],
            ['tech@jetblue.com', true],
            ['ramp@southwest.com', true],
            ['pilot@gmail.com', false],
            ['pilot@notunited.com', false],
            ['pilot@united.com.evil.net', false],
            ['pilot@mail.united.com', false],
            ['pilot@united.co', false],
            ['united.com', false],
        ];
    }

    #[DataProvider('emails')]
    public function test_domain_allowlist(string $email, bool $passes): void
    {
        $v = Validator::make(['email' => $email], ['email' => [new AirlineEmail]]);

        $this->assertSame($passes, $v->passes(), $email);
    }
}
