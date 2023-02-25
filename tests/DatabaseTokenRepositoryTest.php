<?php

namespace AlhajiAki\OtpToken\Tests\Unit;

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken;
use AlhajiAki\OtpToken\DatabaseTokenRepository;
use AlhajiAki\OtpToken\Tests\TestCase;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery as m;
use stdClass;

class DatabaseTokenRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::now());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        m::close();
        Carbon::setTestNow(null);
    }

    /** @test */
    public function create_inserts_new_record_into_table()
    {
        $repo = $this->getRepo();

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

        $this->assertIsNumeric($results);
        $this->assertEquals(6, strlen($results));
    }

    /** @test */
    public function exist_returns_false_if_no_row_found_for_user()
    {
        $repo = $this->getRepo();
        $repo->getConnection()
            ->shouldReceive('table')
            ->once()->with('table')
            ->andReturn($query = m::mock(Builder::class));

        $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
        $query->shouldReceive('where')->once()->with('action', 'valid-action')->andReturn($query);
        $query->shouldReceive('first')->once()->andReturn(null);

        $user = m::mock(CanSendOtpToken::class);
        $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

        $this->assertFalse($repo->exists($user, 'token', 'valid-action', 'field'));
    }

    /** @test */
    public function exist_returns_false_if_record_is_expired()
    {
        $repo = $this->getRepo();
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

        $this->assertFalse($repo->exists($user, 'token', 'valid-action', 'field'));
    }

    /** @test */
    public function exist_returns_true_if_valid_record_exists()
    {
        $repo = $this->getRepo();
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

        $this->assertTrue($repo->exists($user, 'token', 'valid-action', 'field'));
    }

    /** @test */
    public function exist_returns_false_if_invalid_token()
    {
        $repo = $this->getRepo();
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

        $this->assertFalse($repo->exists($user, 'wrong-token', 'valid-action', 'field'));
    }

    /** @test */
    public function exist_returns_false_if_valid_token_but_wrong_action()
    {
        $repo = $this->getRepo();

        $repo->getConnection()
            ->shouldReceive('table')
            ->once()
            ->with('table')
            ->andReturn($query = m::mock(Builder::class));

        $query->shouldReceive('where')->once()->with('column', 'mobile')->andReturn($query);
        $query->shouldReceive('where')->once()->with('action', 'wrong-action')->andReturn($query);
        $date = Carbon::now()->subMinutes(10)->toDateTimeString();

        $query->shouldReceive('first')->once()->andReturn(null);

        $user = m::mock(CanSendOtpToken::class);
        $user->shouldReceive('getColumnForOtpToken')->with('field')->andReturn('mobile');

        $this->assertFalse($repo->exists($user, 'token', 'wrong-action', 'field'));
    }

    /** @test */
    public function recently_created_returns_false_if_no_row_found_for_user()
    {
        $repo = $this->getRepo();
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

        $this->assertFalse($repo->recentlyCreatedToken($user, 'valid-action', 'field'));
    }

    /** @test */
    public function recently_created_returns_false_if_action_is_wrong()
    {
        $repo = $this->getRepo();
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

        $this->assertFalse($repo->recentlyCreatedToken($user, 'valid-action', 'field'));
    }

    /** @test */
    public function recently_created_returns_true_if_record_is_recently_created()
    {
        $repo = $this->getRepo();
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

        $this->assertTrue($repo->recentlyCreatedToken($user, 'valid-action', 'field'));
    }

    /** @test */
    public function recently_created_returns_false_if_valid_record_exists_and_date_not_recent()
    {
        $repo = $this->getRepo();
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

        $this->assertFalse($repo->recentlyCreatedToken($user, 'valid-action', 'field'));
    }

    /** @test */
    public function delete_method_deletes_by_token()
    {
        $repo = $this->getRepo();
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

        $this->assertNull($repo->delete($user, 'valid-action', 'field'));
    }

    /** @test */
    public function delete_expired_method_deletes_expired_tokens()
    {
        $repo = $this->getRepo();
        $repo->getConnection()
            ->shouldReceive('table')
            ->once()
            ->with('table')
            ->andReturn($query = m::mock(Builder::class));

        $query->shouldReceive('where')
            ->once()
            ->with('created_at', '<', m::any())->andReturn($query);

        $query->shouldReceive('delete')->once()->andReturn(1);

        $this->assertNull($repo->deleteExpired());
    }

    protected function getRepo()
    {
        // This replaces the mock in the dependency manager.
        $connection = $this->app->instance(Connection::class, m::mock(Connection::class));
        $hasher = $this->app->instance(Hasher::class, m::mock(Hasher::class));

        return new DatabaseTokenRepository(
            $connection,
            $hasher,
            'table',
            'key'
        );
    }
}
