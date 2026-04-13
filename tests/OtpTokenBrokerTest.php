<?php

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken;
use AlhajiAki\OtpToken\Contracts\OtpTokenBroker as OtpTokenBrokerContract;
use AlhajiAki\OtpToken\OtpTokenBroker;
use AlhajiAki\OtpToken\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Arr;
use Mockery as m;
use UnexpectedValueException;

afterEach(function () {
    m::close();
});

function getMocks(): array
{
    return [
        'tokens' => m::mock(TokenRepositoryInterface::class),
        'users' => m::mock(UserProvider::class),
    ];
}

function getBroker(array $mocks): OtpTokenBroker
{
    return new OtpTokenBroker($mocks['tokens'], $mocks['users']);
}

it('user is not found error is returned', function () {
    $mocks = getMocks();

    $broker = $this->getMockBuilder(OtpTokenBroker::class)
        ->onlyMethods(['getUser'])
        ->setConstructorArgs(array_values($mocks))
        ->getMock();

    $broker->expects($this->once())->method('getUser')->willReturn(null);

    expect(
        $broker->sendOtpToken(['credentials', 'action' => 'action'], function () {
            //
        })
    )->toBe(OtpTokenBrokerContract::INVALID_USER);
});

it('token is recently created error is returned', function () {
    $mocks = getMocks();
    $broker = getBroker($mocks);

    $mocks['users']->shouldReceive('retrieveByCredentials')
        ->once()
        ->with(['foo'])
        ->andReturn($user = m::mock(CanSendOtpToken::class));

    $mocks['tokens']->shouldReceive('recentlyCreatedToken')
        ->once()
        ->with($user, 'action', 'field')
        ->andReturn(true);

    $user->shouldReceive('sendOtpTokenNotification')->with('token');

    expect(
        $broker->sendOtpToken(['foo', 'action' => 'action', 'field' => 'field'], function () {
            //
        })
    )->toBe(OtpTokenBrokerContract::OTP_TOKEN_THROTTLED);
});

it('get user throws exception if user does not implement can send otp token', function () {
    $broker = getBroker($mocks = getMocks());

    $mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(['foo'])->andReturn('bar');

    $broker->getUser(['foo']);
})->throws(UnexpectedValueException::class, 'User must implement CanSendOtpToken interface.');

it('user is retrieved by credentials', function () {
    $broker = getBroker($mocks = getMocks());

    $mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(['foo'])->andReturn($user = m::mock(CanSendOtpToken::class));

    expect($broker->getUser(['foo']))->toBe($user);
});

it('broker creates token without error', function () {
    $mocks = getMocks();
    $broker = getBroker($mocks);

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

    expect(
        $broker->sendOtpToken(['foo', 'action' => 'action', 'field' => 'field'], function () {
            //
        })
    )->toBe(OtpTokenBrokerContract::OTP_TOKEN_SENT);
});

it('send otp token executes the callback passed to it', function () {
    $executed = false;

    $closure = function () use (&$executed) {
        $executed = true;
    };

    $mocks = getMocks();
    $broker = getBroker($mocks);

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

    expect(
        $broker->sendOtpToken(['foo', 'action' => 'action', 'field' => 'field'], $closure)
    )->toBe(OtpTokenBrokerContract::OTP_TOKEN_SENT);

    expect($executed)->toBeTrue();
});

it('invalid user is returned if credentials are wrong when performing an action', function () {
    $broker = getBroker($mocks = getMocks());

    $mocks['users']->shouldReceive('retrieveByCredentials')->once()->with(['creds'])->andReturn(null);

    expect($broker->performAction(['creds'], function () {
        //
    }))->toBe(OtpTokenBrokerContract::INVALID_USER);
});

it('invalid token is returned if token does not exist when performing an action', function () {
    $creds = ['token' => 'token', 'action' => 'action', 'field' => 'field'];
    $broker = getBroker($mocks = getMocks());

    $mocks['users']->shouldReceive('retrieveByCredentials')
        ->once()
        ->with(Arr::except($creds, ['token', 'action', 'field']))
        ->andReturn($user = m::mock(CanSendOtpToken::class));

    $mocks['tokens']->shouldReceive('exists')
        ->with($user, 'token', 'action', 'field')
        ->andReturn(false);

    expect($broker->performAction($creds, function () {
        //
    }))->toBe(OtpTokenBrokerContract::INVALID_TOKEN);
});

it('performs action token and calls callback', function () {
    unset($_SERVER['__otptoken.test']);

    $broker = $this->getMockBuilder(OtpTokenBroker::class)
        ->onlyMethods(['validateOtpToken'])
        ->setConstructorArgs(array_values($mocks = getMocks()))
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

    expect(
        $broker->performAction(['token' => 'token', 'action' => 'action', 'field' => 'field'], $callback)
    )->toBe(OtpTokenBrokerContract::ACTION_COMPLETED);

    expect($_SERVER['__otptoken.test'])->toBe(['user' => $user]);
});
