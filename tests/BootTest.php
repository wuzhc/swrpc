<?php

namespace SwrpcTests;


use PHPUnit\Framework\TestCase;

/**
 * Class BootTest
 *
 * @author wuzhc
 * @internal
 */
abstract class BootTest extends TestCase
{
    const PID_FILE = __DIR__ . '/swrpc.pid';
    const SERVER_LOG = __DIR__ . '/swrpc.log';
    const SERVER_SCRIPT = __DIR__ . '/server.sh';

    public static function setUpBeforeClass(): void
    {
        // fwrite(STDOUT, 'Starting rpc server...' . PHP_EOL);
        $cmd = 'nohup ' . self::SERVER_SCRIPT . ' > ' . self::SERVER_LOG . ' 2>&1 &';
        shell_exec($cmd);
        sleep(5);

        self::assertFileExists(self::PID_FILE, 'Run rpc server failed: ' . $cmd . '');
        $pid = file_get_contents(self::PID_FILE);
        self::assertNotEmpty($pid, 'Failed to start the rpc server.');

        $res = shell_exec('ps aux | grep ' . $pid . ' | wc -l');
        self::assertGreaterThanOrEqual(1, intval($res), 'Failed to start the rpc server.');

        // fwrite(STDOUT, 'Rpc server started successfully.' . PHP_EOL);
    }

    public static function tearDownAfterClass(): void
    {
        if (\file_exists(self::PID_FILE)) {
            $pid = file_get_contents(self::PID_FILE);
            \shell_exec('kill -15 ' . $pid);
            if (\file_exists(self::PID_FILE)) {
                \unlink(self::PID_FILE);
            }
            \sleep(1);
        }
    }
}
