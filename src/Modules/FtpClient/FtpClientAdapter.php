<?php

namespace FTPApp\Modules\FtpClient;

use FTPApp\Modules\FtpAdapter;
use Lazzard\FtpClient\Config\FtpConfig;
use Lazzard\FtpClient\Connection\ConnectionInterface;
use Lazzard\FtpClient\Connection\FtpConnection;
use Lazzard\FtpClient\Connection\FtpSSLConnection;
use Lazzard\FtpClient\Exception\FtpClientException;
use Lazzard\FtpClient\FtpClient;
use Lazzard\FtpClient\FtpWrapper;

class FtpClientAdapter implements FtpAdapter
{
    /** @var ConnectionInterface */
    public $connection;

    /** @var FtpConfig */
    public $config;

    /** @var FtpClient */
    public $client;

    /**
     * @inheritDoc
     */
    public function openConnection($config)
    {
        try {
            $connectionInitializer = FtpConnection::class;
            if (isset($config['useSsl']) && $config['useSsl']) {
                $connectionInitializer = FtpSSLConnection::class;
            }

            $connection = new $connectionInitializer(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['port']
            );

            $connection->open();

            $this->connection = $connection;
            $this->config     = new FtpConfig($connection);
            $this->client     = new FtpClient($connection);

            if (isset($config['usePassive']) && $config['usePassive']) {
                $this->setPassive(true);
            }
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    /**
     * @inheritDoc
     */
    public function setPassive($bool)
    {
        $this->config->setPassive($bool);
    }

    /**
     * @inheritDoc
     */
    public function browse($dir)
    {
        try {
            // escape dir spaces (rawlist bug)
            $list = $this->client->listDirectoryDetails(str_replace(' ', '\ ', $dir));

            $files = [];
            foreach ($list as $file) {
                $files[] = [
                    'name'         => $file['name'],
                    'type'         => $file['type'],
                    'size'         => $file['size'],
                    'modifiedTime' => sprintf("%s %s %s", $file['day'], $file['month'], $file['time']),
                    'permissions'  => $file['chmod']
                ];
            }

            return $files;
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function addFile($file)
    {
        try {
            return $this->client->createFile(urldecode(ltrim($file, '/')));
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function addFolder($dir)
    {
        try {
            return $this->client->createDirectory(urldecode(ltrim($dir, '/')));
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function getFileContent($file)
    {
        try {
            return $this->client->getFileContent($file);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function updateFileContent($file, $content)
    {
        try {
            return $this->client->createFile($file, $content);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function remove($files)
    {
        try {
            foreach ($files as $file) {
                if ($this->client->isDir($file)) {
                    $this->client->removeDirectory($file);
                } else {
                    $this->client->removeFile($file);
                }
            }
            return true;
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function rename($file, $newName)
    {
        try {
            return $this->client->rename($file, $newName);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function getDirectoryTree()
    {
        try {
            return $this->client->listDirectoryDetails('/', true, FtpClient::DIR_TYPE);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function move($file, $newPath)
    {
        try {
            return $this->client->move(ltrim($file, '/'), $newPath);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    public function permissions($file, $permissions)
    {
        try {
            return $this->client->setPermissions($file, $permissions);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex));
        }
    }

    /**
     * Normalize FtpClient exception messages.
     *
     * Example:
     *
     * from:
     * [ConnectionException] - Failed to connect to remote server.
     *
     * to:
     * Failed to connect to remote server.
     *
     * @param FtpClientException $exception
     *
     * @return string
     */
    protected function normalizeExceptionMessage($exception)
    {
        return preg_replace('/([\[\w\]]+)\s-\s/i', '', $exception->getMessage());
    }
}
