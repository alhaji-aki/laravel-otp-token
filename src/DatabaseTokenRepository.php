<?php

namespace AlhajiAki\OtpToken;

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken as CanSendOtpTokenContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;

class DatabaseTokenRepository implements TokenRepositoryInterface
{
    /**
     * The database connection instance.
     *
     * @var \Illuminate\Database\ConnectionInterface
     */
    protected $connection;

    /**
     * The Hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The token database table.
     *
     * @var string
     */
    protected $table;

    /**
     * The hashing key.
     *
     * @var string
     */
    protected $hashKey;

    /**
     * The number of seconds a token should last.
     *
     * @var int
     */
    protected $expires;

    /**
     * Minimum number of seconds before re-redefining the token.
     *
     * @var int
     */
    protected $throttle;

    /**
     * Create a new token repository instance.
     *
     * @param  \Illuminate\Database\ConnectionInterface  $connection
     * @param  \Illuminate\Contracts\Hashing\Hasher  $hasher
     * @param  string  $table
     * @param  string  $hashKey
     * @param  int  $expires
     * @param  int  $throttle
     * @return void
     */
    public function __construct(
        ConnectionInterface $connection,
        HasherContract $hasher,
        $table,
        $hashKey,
        $expires = 60,
        $throttle = 60
    ) {
        $this->table = $table;
        $this->hasher = $hasher;
        $this->hashKey = $hashKey;
        $this->expires = $expires * 60;
        $this->connection = $connection;
        $this->throttle = $throttle;
    }

    /**
     * Create a new token record.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return string
     */
    public function create(CanSendOtpTokenContract $user, $action, $field)
    {
        $column = $user->getColumnForOtpToken($field);

        $this->deleteExisting($user, $action, $field);

        // We will create a new, random token for the user so that we can e-mail them
        // Then we will insert a record in the database so that we can verify the token later.
        $token = $this->createNewToken();

        $this->getTable()->insert($this->getPayload($column, $token, $action));

        return $token;
    }

    /**
     * Delete all existing reset tokens from the database.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return int
     */
    protected function deleteExisting(CanSendOtpTokenContract $user, $action, $field)
    {
        return $this->getTable()
            ->where('column', $user->getColumnForOtpToken($field))
            ->where('action', $action)
            ->delete();
    }

    /**
     * Build the record payload for the table.
     *
     * @param  string  $column
     * @param  string  $token
     * @param  string  $action
     * @return array
     */
    protected function getPayload($column, $token, $action)
    {
        return [
            'column' => $column,
            'token' => $this->hasher->make($token),
            'action' => $action,
            'created_at' => new Carbon(),
        ];
    }

    /**
     * Determine if a token record exists and is valid.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param  string  $token
     * @param string $action
     * @param string $field
     * @return bool
     */
    public function exists(CanSendOtpTokenContract $user, $token, $action, $field)
    {
        $record = (array) $this->getRecord($user, $action, $field);

        return $record &&
            ! $this->tokenExpired($record['created_at']) &&
            $this->hasher->check($token, $record['token']);
    }

    /**
     * Determine if the token has expired.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function tokenExpired($createdAt)
    {
        return Carbon::parse($createdAt)->addSeconds($this->expires)->isPast();
    }

    /**
     * Determine if the given user recently created an otp token.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return bool
     */
    public function recentlyCreatedToken(CanSendOtpTokenContract $user, $action, $field)
    {
        $record = (array) $this->getRecord($user, $action, $field);

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    /**
     * Determine if the token was recently created.
     *
     * @param  string  $createdAt
     * @return bool
     */
    protected function tokenRecentlyCreated($createdAt)
    {
        if ($this->throttle <= 0) {
            return false;
        }

        return Carbon::parse($createdAt)->addSeconds(
            $this->throttle
        )->isFuture();
    }

    /**
     * Delete a token record by user.
     *
     * @param  \AlhajiAki\OtpToken\Contracts\CanSendOtpToken  $user
     * @param string $action
     * @param string $field
     * @return void
     */
    public function delete(CanSendOtpTokenContract $user, $action, $field)
    {
        $this->deleteExisting($user, $action, $field);
    }

    /**
     * Delete expired tokens.
     *
     * @return void
     */
    public function deleteExpired()
    {
        $expiredAt = Carbon::now()->subSeconds($this->expires);

        $this->getTable()->where('created_at', '<', $expiredAt)->delete();
    }

    /**
     * Create a new token for the user.
     *
     * @return string
     */
    public function createNewToken()
    {
        return rand(100000, 999999);
    }

    /**
     * Get the database connection instance.
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Begin a new database query against the table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getTable()
    {
        return $this->connection->table($this->table);
    }

    protected function getRecord($user, $action, $field)
    {
        return $this->getTable()->where(
            'column',
            $user->getColumnForOtpToken($field)
        )->where('action', $action)->first();
    }

    /**
     * Get the hasher instance.
     *
     * @return \Illuminate\Contracts\Hashing\Hasher
     */
    public function getHasher()
    {
        return $this->hasher;
    }
}
