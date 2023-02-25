<?php

namespace AlhajiAki\OtpToken;

use AlhajiAki\OtpToken\Contracts\CanSendOtpToken as CanSendOtpTokenContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

class DatabaseTokenRepository implements TokenRepositoryInterface
{
    /**
     * Create a new token repository instance.
     */
    public function __construct(
        protected ConnectionInterface $connection,
        protected HasherContract $hasher,
        protected string $table,
        protected string $hashKey,
        protected int $expires = 60,
        protected int $throttle = 60
    ) {
    }

    /**
     * Create a new token record.
     */
    public function create(CanSendOtpTokenContract $user, string $action, string $field): string
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
     */
    protected function deleteExisting(CanSendOtpTokenContract $user, string $action, string $field): int
    {
        return $this->getTable()
            ->where('column', $user->getColumnForOtpToken($field))
            ->where('action', $action)
            ->delete();
    }

    /**
     * Build the record payload for the table.
     *
     * @return array<string, string>
     */
    protected function getPayload(string $column, string $token, string $action): array
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
     */
    public function exists(CanSendOtpTokenContract $user, string $token, string $action, string $field): bool
    {
        $record = (array) $this->getRecord($user, $action, $field);

        return $record &&
            ! $this->tokenExpired($record['created_at']) &&
            $this->hasher->check($token, $record['token']);
    }

    /**
     * Determine if the token has expired.
     */
    protected function tokenExpired(string $createdAt): bool
    {
        return Carbon::parse($createdAt)->addMinutes($this->expires)->isPast();
    }

    /**
     * Determine if the given user recently created an otp token.
     */
    public function recentlyCreatedToken(CanSendOtpTokenContract $user, string $action, string $field): bool
    {
        $record = (array) $this->getRecord($user, $action, $field);

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    /**
     * Determine if the token was recently created.
     */
    protected function tokenRecentlyCreated(string $createdAt): bool
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
     */
    public function delete(CanSendOtpTokenContract $user, string $action, string $field): void
    {
        $this->deleteExisting($user, $action, $field);
    }

    /**
     * Delete expired tokens.
     */
    public function deleteExpired(): void
    {
        $expiredAt = Carbon::now()->subSeconds($this->expires);

        $this->getTable()->where('created_at', '<', $expiredAt)->delete();
    }

    /**
     * Create a new token for the user.
     */
    public function createNewToken(): string
    {
        return (string) rand(100000, 999999);
    }

    /**
     * Get the database connection instance.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Begin a new database query against the table.
     */
    protected function getTable(): Builder
    {
        return $this->connection->table($this->table);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model|object|static|null
     */
    protected function getRecord(CanSendOtpTokenContract $user, string $action, string $field)
    {
        return $this->getTable()->where(
            'column',
            $user->getColumnForOtpToken($field)
        )->where('action', $action)->first();
    }

    /**
     * Get the hasher instance.
     */
    public function getHasher(): HasherContract
    {
        return $this->hasher;
    }
}
