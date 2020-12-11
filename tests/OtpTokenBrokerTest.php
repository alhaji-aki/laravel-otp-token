<?php

namespace AlhajiAki\OtpToken\Tests\Unit;

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken;
use AlhajiAki\OtpToken\Contracts\OtpTokenBroker as OtpTokenBrokerContract;
use AlhajiAki\OtpToken\OtpTokenBroker;
use AlhajiAki\OtpToken\Tests\TestCase;
use AlhajiAki\OtpToken\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Arr;
use Mockery as m;
// use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class OtpTokenBrokerTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    /** @test */
    public function user_is_not_found_error_is_returned()
    {
        $mocks = $this->getMocks();

        $broker = $this->getMockBuilder(OtpTokenBroker::class)
            ->onlyMethods(['getUser'])
            ->setConstructorArgs(array_values($mocks))
            ->getMock();

        $broker->expects($this->once())->method('getUser')->willReturn(null);

        $this->assertSame(
            OtpTokenBrokerContract::INVALID_USER,
            $broker->sendOtpToken(['credentials', 'action' => 'action'], function () {
                //
            })
        );
    }

    /** @test */
    public function token_is_recently_created_error_is_returned()
    {
        $mocks = $this->getMocks();

        $broker = $this->getMockBuilder(OtpTokenBroker::class)
            ->addMethods(['sendToken'])
            ->setConstructorArgs(array_values($mocks))
            ->getMock();

        $mocks['users']->shouldReceive('retrieveByCredentials')
            ->once()
            ->with(['foo'])
            ->andReturn($user = m::mock(CanSendOtpToken::class));

        $mocks['tokens']->shouldReceive('recentlyCreatedToken')
            ->once()
            ->with($user, 'action', 'field')
            ->andReturn(true);

        $user->shouldReceive('sendOtpTokenNotification')->with('token');

        $this->assertSame(
            OtpTokenBrokerContract::OTP_TOKEN_THROTTLED,
            $broker->sendOtpToken(['foo', 'action' => 'action', 'field' => 'field'], function () {
                //
            })
        );
    }

    /** @test */
    public function get_user_throws_exception_if_user_does_not_implement_can_send_otp_token()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('User must implement CanSendOtpToken interface.');

        $broker = $this->getBroker($mocks = $this->getMocks());

        $mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(['foo'])->andReturn('bar');

        $broker->getUser(['foo']);
    }

    /** @test */
    public function user_is_retrieved_by_credentials()
    {
        $broker = $this->getBroker($mocks = $this->getMocks());

        $mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(['foo'])->andReturn($user = m::mock(CanSendOtpToken::class));

        $this->assertEquals($user, $broker->getUser(['foo']));
    }

    /** @test */
    public function broker_creates_token_without_error()
    {
        $mocks = $this->getMocks();

        $broker = $this->getMockBuilder(OtpTokenBroker::class)
            ->addMethods(['sendToken'])
            ->setConstructorArgs(array_values($mocks))
            ->getMock();

        $mocks['users']->shouldReceive('retrieveByCredentials')
            ->once()
            ->with(['foo'])
            ->andReturn($user = m::mock(CanSendOtpToken::class));

        $mocks['tokens']->shouldReceive('recentlyCreatedToken')
            ->once()
            ->with($user, 'action', 'field')
            ->andReturn(false);

        $mocks['tokens']->shouldReceive('create')
            ->once()
            ->with($user, 'action', 'field')
            ->andReturn('123456');

        $this->assertSame(
            OtpTokenBrokerContract::OTP_TOKEN_SENT,
            $broker->sendOtpToken(['foo', 'action' => 'action', 'field' => 'field'], function () {
                //
            })
        );
    }

    /** @test */
    public function send_otp_token_executes_the_callback_passed_to_it()
    {
        $executed = false;

        $closure = function () use (&$executed) {
            $executed = true;
        };

        $mocks = $this->getMocks();

        $broker = $this->getMockBuilder(OtpTokenBroker::class)
            ->addMethods(['sendToken'])
            ->setConstructorArgs(array_values($mocks))->getMock();

        $mocks['users']->shouldReceive('retrieveByCredentials')
            ->once()
            ->with(['foo'])
            ->andReturn($user = m::mock(CanSendOtpToken::class));

        $mocks['tokens']->shouldReceive('recentlyCreatedToken')
            ->once()
            ->with($user, 'action', 'field')
            ->andReturn(false);

        $mocks['tokens']->shouldReceive('create')
            ->once()
            ->with($user, 'action', 'field')
            ->andReturn('token');

        $this->assertEquals(
            OtpTokenBrokerContract::OTP_TOKEN_SENT,
            $broker->sendOtpToken(['foo', 'action' => 'action', 'field' => 'field'], $closure)
        );

        $this->assertTrue($executed);
    }

    /** @test */
    public function invalid_user_is_returned_if_credentials_are_wrong_when_performing_an_action()
    {
        $broker = $this->getBroker($mocks = $this->getMocks());

        $mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(['creds'])->andReturn(null);

        $this->assertSame(OtpTokenBrokerContract::INVALID_USER, $broker->performAction(['creds'], function () {
            //
        }));
    }

    /** @test */
    public function invalid_token_is_returned_if_token_does_not_exist_when_performing_an_action()
    {
        $creds = ['token' => 'token', 'action' => 'action', 'field' => 'field'];
        $broker = $this->getBroker($mocks = $this->getMocks());

        $mocks['users']->shouldReceive('retrieveByCredentials')
            ->once()
            ->with(Arr::except($creds, ['token', 'action', 'field']))
            ->andReturn($user = m::mock(CanSendOtpToken::class));

        $mocks['tokens']->shouldReceive('exists')
            ->with($user, 'token', 'action', 'field')
            ->andReturn(false);

        $this->assertSame(OtpTokenBrokerContract::INVALID_TOKEN, $broker->performAction($creds, function () {
            //
        }));
    }

    /** @test */
    public function performs_action_token_and_calls_callback()
    {
        unset($_SERVER['__otptoken.test']);
        $broker = $this->getMockBuilder(OtpTokenBroker::class)
            ->onlyMethods(['validateOtpToken'])
            ->setConstructorArgs(array_values($mocks = $this->getMocks()))
            ->getMock();

        $broker->expects($this->once())
            ->method('validateOtpToken')
            ->willReturn($user = m::mock(CanSendOtpToken::class));

        $mocks['tokens']->shouldReceive('delete')
            ->once()
            ->with($user, 'action', 'field');

        $callback = function ($user) {
            $_SERVER['__otptoken.test'] = compact('user');

            return 'foo';
        };

        $this->assertSame(
            OtpTokenBrokerContract::ACTION_COMPLETED,
            $broker->performAction(['token' => 'token', 'action' => 'action', 'field' => 'field'], $callback)
        );
        $this->assertEquals(['user' => $user], $_SERVER['__otptoken.test']);
    }

    protected function getBroker($mocks)
    {
        return new OtpTokenBroker($mocks['tokens'], $mocks['users']);
    }

    protected function getMocks()
    {
        return [
            'tokens' => m::mock(TokenRepositoryInterface::class),
            'users' => m::mock(UserProvider::class),
        ];
    }
}
