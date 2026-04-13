<?php

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken;
use AlhajiAki\OtpToken\DatabaseTokenRepository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery as m;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::now());
});

afterEach(function () {
    m::close();
    Carbon::setTestNow(null);
});

function getRepo(): DatabaseTokenRepository
{
    $connection = app()->instance(Connection::class, m::mock(Connection::class));
    $hasher = app()->instance(Hasher::class, m::mock(Hasher::class));

    return new DatabaseTokenRepository(
        $connection,
        $hasher,
        'table',
        'key'
    );
}

it('create inserts new record into table', function () {
    $repo = getRepo();

    $repo->getHasher()
        ->shouldReceive('make')
        ->once()
        ->andReturn('hashed-token');

    $repo->getConnection()
        ->shouldReceive('table')
        ->times(2)
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $query->shouldReceive('delete')->once()->andReturn(1);
    $query->shouldReceive('insert')->once();

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    $results = $repo->create($user, 'valid-action', 'field');

    expect($results)->toBeNumeric();
    expect(strlen($results))->toBe(6);
});

it('exist returns false if no row found for user', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $query->shouldReceive('first')->once()->andReturn(null);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->exists($user, 'token', 'valid-action', 'field'))->toBeFalse();
});

it('exist returns false if record is expired', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $date = Carbon::now()->subSeconds(300000)->toDateTimeString();

    $query->shouldReceive('first')->once()->andReturn((object) ['created_at' => $date, 'token' => 'hashed-token']);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->exists($user, 'token', 'valid-action', 'field'))->toBeFalse();
});

it('exist returns true if valid record exists', function () {
    $repo = getRepo();
    $repo->getHasher()
        ->shouldReceive('check')
        ->once()
        ->with('token', 'hashed-token')
        ->andReturn(true);

    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $date = Carbon::now()->subMinutes(10)->toDateTimeString();

    $query->shouldReceive('first')->once()->andReturn((object) ['created_at' => $date, 'token' => 'hashed-token']);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->exists($user, 'token', 'valid-action', 'field'))->toBeTrue();
});

it('exist returns false if invalid token', function () {
    $repo = getRepo();
    $repo->getHasher()
        ->shouldReceive('check')
        ->once()
        ->with('wrong-token', 'hashed-token')
        ->andReturn(false);

    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);

    $date = Carbon::now()->subMinutes(10)->toDateTimeString();
    $query->shouldReceive('first')->once()->andReturn((object) ['created_at' => $date, 'token' => 'hashed-token']);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->exists($user, 'wrong-token', 'valid-action', 'field'))->toBeFalse();
});

it('exist returns false if valid token but wrong action', function () {
    $repo = getRepo();

    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'wrong-action')->andReturn($query);

    $query->shouldReceive('first')->once()->andReturn(null);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->exists($user, 'token', 'wrong-action', 'field'))->toBeFalse();
});

it('recently created returns false if no row found for user', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $query->shouldReceive('first')->once()->andReturn(null);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->recentlyCreatedToken($user, 'valid-action', 'field'))->toBeFalse();
});

it('recently created returns false if action is wrong', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $query->shouldReceive('first')->once()->andReturn(null);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->recentlyCreatedToken($user, 'valid-action', 'field'))->toBeFalse();
});

it('recently created returns true if record is recently created', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $date = Carbon::now()->subSeconds(59)->toDateTimeString();

    $query->shouldReceive('first')
        ->once()
        ->andReturn((object) ['created_at' => $date, 'token' => 'hashed-token']);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->recentlyCreatedToken($user, 'valid-action', 'field'))->toBeTrue();
});

it('recently created returns false if valid record exists and date not recent', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);

    $date = Carbon::now()->subSeconds(61)->toDateTimeString();
    $query->shouldReceive('first')->once()->andReturn((object) ['created_at' => $date, 'token' => 'hashed-token']);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->recentlyCreatedToken($user, 'valid-action', 'field'))->toBeFalse();
});

it('delete method deletes by token', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
    $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
    $query->shouldReceive('delete')->once()->andReturn(1);

    $user = m::mock(CanSendOtpToken::class);
    $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

    expect($repo->delete($user, 'valid-action', 'field'))->toBeNull();
});

it('delete expired method deletes expired tokens', function () {
    $repo = getRepo();
    $repo->getConnection()
        ->shouldReceive('table')
        ->once()
        ->with('table')
        ->andReturn($query = m::mock(Builder::class));

    $query->shouldReceive('where')
        ->once()
        ->with('created_at', '<', m::any())->andReturn($query);

    $query->shouldReceive('delete')->once()->andReturn(1);

    expect($repo->deleteExpired())->toBeNull();
});
