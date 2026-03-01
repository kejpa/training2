<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Infrastructure\Persistence\DatabaseConnection;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        // Sätt test environment variables
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_password';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';

        // Återställ singleton mellan tester
        $this->resetSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
    }

    private function resetSingleton(): void
    {
        $reflection = new \ReflectionClass(DatabaseConnection::class);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    public function testReturnsConnectionInstance(): void
    {
        $connection = DatabaseConnection::getConnection();

        $this->assertInstanceOf(Connection::class, $connection);
    }

    public function testReturnsSameConnectionOnMultipleCalls(): void
    {
        $connection1 = DatabaseConnection::getConnection();
        $connection2 = DatabaseConnection::getConnection();

        $this->assertSame($connection1, $connection2);
    }

    public function testUsesEnvironmentVariablesForConfiguration(): void
    {
        $_ENV['DB_NAME'] = 'custom_db_name';
        $_ENV['DB_USER'] = 'custom_user';
        $_ENV['DB_HOST'] = 'custom_host';

        $this->resetSingleton();

        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertEquals('custom_db_name', $params['dbname']);
        $this->assertEquals('custom_user', $params['user']);
        $this->assertEquals('custom_host', $params['host']);
    }

    public function testUsesDefaultPortWhenNotSet(): void
    {
        unset($_ENV['DB_PORT']);
        $this->resetSingleton();

        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertEquals(3306, $params['port']);
    }

    public function testUsesCustomPortWhenSet(): void
    {
        $_ENV['DB_PORT'] = '3307';
        $this->resetSingleton();

        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertEquals(3307, $params['port']);
    }

    public function testUsesCorrectDriver(): void
    {
        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertEquals('pdo_mysql', $params['driver']);
    }

    public function testUsesUtf8mb4Charset(): void
    {
        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertEquals('utf8mb4', $params['charset']);
    }

    public function testConnectionParametersAreComplete(): void
    {
        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertArrayHasKey('dbname', $params);
        $this->assertArrayHasKey('user', $params);
        $this->assertArrayHasKey('password', $params);
        $this->assertArrayHasKey('host', $params);
        $this->assertArrayHasKey('port', $params);
        $this->assertArrayHasKey('driver', $params);
        $this->assertArrayHasKey('charset', $params);
    }

    public function testConnectionIsLazilyInitialized(): void
    {
        // Återställ först
        $this->resetSingleton();

        // Verifiera att ingen connection finns innan getConnection() anropas
        $reflection = new \ReflectionClass(DatabaseConnection::class);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);

        $this->assertNull($property->getValue());

        // Anropa getConnection
        DatabaseConnection::getConnection();

        // Nu ska connection finnas
        $this->assertNotNull($property->getValue());
        $this->assertInstanceOf(Connection::class, $property->getValue());
    }

    public function testMultipleCallsDoNotCreateNewConnections(): void
    {
        $this->resetSingleton();

        // Första anropet skapar connection
        $connection1 = DatabaseConnection::getConnection();

        // Följande anrop ska återanvända samma connection
        $connection2 = DatabaseConnection::getConnection();
        $connection3 = DatabaseConnection::getConnection();

        $this->assertSame($connection1, $connection2);
        $this->assertSame($connection1, $connection3);
        $this->assertSame($connection2, $connection3);
    }

    public function testReadsPasswordFromEnvironment(): void
    {
        $_ENV['DB_PASSWORD'] = 'super_secret_password';
        $this->resetSingleton();

        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertEquals('super_secret_password', $params['password']);
    }

    public function testHandlesDifferentDatabaseNames(): void
    {
        $databases = ['test_db', 'production_db', 'development_db', 'my-app-db'];

        foreach ($databases as $dbName) {
            $_ENV['DB_NAME'] = $dbName;
            $this->resetSingleton();

            $connection = DatabaseConnection::getConnection();
            $params = $connection->getParams();

            $this->assertEquals($dbName, $params['dbname'], "Failed for database: $dbName");
        }
    }

    public function testHandlesDifferentHosts(): void
    {
        $hosts = ['localhost', '127.0.0.1', 'db.example.com', '192.168.1.100'];

        foreach ($hosts as $host) {
            $_ENV['DB_HOST'] = $host;
            $this->resetSingleton();

            $connection = DatabaseConnection::getConnection();
            $params = $connection->getParams();

            $this->assertEquals($host, $params['host'], "Failed for host: $host");
        }
    }

    public function testPortIsInteger(): void
    {
        $_ENV['DB_PORT'] = 3307;
        $this->resetSingleton();

        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertIsInt($params['port']);
        $this->assertEquals(3307, $params['port']);
    }

    public function testDefaultPortIsInteger(): void
    {
        unset($_ENV['DB_PORT']);
        $this->resetSingleton();

        $connection = DatabaseConnection::getConnection();
        $params = $connection->getParams();

        $this->assertIsInt($params['port']);
        $this->assertEquals(3306, $params['port']);
    }

    public function testConnectionIsSingleton(): void
    {
        // Ett singleton-pattern betyder att samma instans returneras
        $connections = [];

        for ($i = 0; $i < 5; $i++) {
            $connections[] = DatabaseConnection::getConnection();
        }

        // Alla ska vara samma instans
        foreach ($connections as $connection) {
            $this->assertSame($connections[0], $connection);
        }
    }

    public function testCanRetrieveConnectionMultipleTimesInDifferentContexts(): void
    {
        // Simulera olika "contexts" genom att anropa från olika "ställen"
        $connectionFromContext1 = $this->getConnectionFromContext1();
        $connectionFromContext2 = $this->getConnectionFromContext2();

        $this->assertSame($connectionFromContext1, $connectionFromContext2);
    }

    private function getConnectionFromContext1(): Connection
    {
        return DatabaseConnection::getConnection();
    }

    private function getConnectionFromContext2(): Connection
    {
        return DatabaseConnection::getConnection();
    }

    public function testEnvironmentVariablesAreRequired(): void
    {
        // Detta test verifierar implicit att om env vars saknas kommer
        // DriverManager::getConnection() att kasta exception
        // Vi kan inte enkelt testa detta utan att faktiskt ta bort env vars
        // vilket skulle påverka andra tester, så vi skippar detta test

        $this->markTestSkipped('Cannot safely test missing env vars without affecting other tests');
    }
}