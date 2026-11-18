<?php

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

it('refuses inspection when the target is not explicitly MySQL', function (): void {
    config(['database.default' => 'sqlite']);

    $this->artisan('legacy:inspect-environment')
        ->expectsOutputToContain('O destino precisa usar a conexão MySQL')
        ->assertFailed();
});

it('refuses identical source and target databases without modifying records', function (): void {
    config(['database.default' => 'mysql']);
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('selectOne')->twice()
        ->with('SELECT @@server_uuid AS server_uuid, DATABASE() AS database_name')
        ->andReturn((object) ['server_uuid' => 'same-server', 'database_name' => 'same_database']);
    DB::shouldReceive('connection')->with('legacy')->once()->andReturn($connection);
    DB::shouldReceive('connection')->with('mysql')->once()->andReturn($connection);

    $this->artisan('legacy:inspect-environment')
        ->expectsOutputToContain('Origem e destino apontam para o mesmo banco')
        ->assertFailed();
});
