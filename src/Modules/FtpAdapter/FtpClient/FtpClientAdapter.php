<?php

namespace FTPApp\Modules\FtpAdapter\FtpClient;

use FTPApp\Http\HttpResponse;
use FTPApp\Modules\FtpAdapter\FtpAdapter;
use Lazzard\FtpClient\Config\FtpConfig;
use Lazzard\FtpClient\Connection\ConnectionInterface;
use Lazzard\FtpClient\Connection\FtpConnection;
use Lazzard\FtpClient\Connection\FtpSSLConnection;
use Lazzard\FtpClient\Exception\FtpClientException;
use Lazzard\FtpClient\FtpClient;

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
            $params = [
                $config['host'],
                $config['username'],
                $config['password'],
                $config['port'],
                $config['timeout'],
            ];

            if (isset($config['useSsl']) && $config['useSsl']) {
                try {
                    $connection = new FtpSSLConnection(...$params);
                } catch (FtpClientException $ex) {
                    $connection = new FtpConnection(...$params);
                }
            } else {
                $connection = new FtpConnection(...$params);
            }

            $connection->open();

            $this->connection = $connection;
            $this->config     = new FtpConfig($connection);
            $this->client     = new FtpClient($connection);

            if (isset($config['usePassive']) && $config['usePassive']) {
                $this->config->setPassive($config['usePassive']);
                $this->config->setAutoSeek($config['autoSeek']);
            }
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function browse($dir)
    {
        try {
            $list = $this->client->listDirDetails(trim($dir, '/'));

            $files = [];
            $dirs = [];
            foreach ($list as $file) {
                $info = [
                    'name'         => $file['name'],
                    'type'         => $file['type'],
                    'size'         => $file['size'],
                    'modifiedTime' => sprintf("%s %s %s", $file['day'], $file['month'], $file['time']),
                    'permissions'  => $file['chmod'],
                    'path'         => $file['path'],
                    'owner'        => $file['owner'],
                    'group'        => $file['group'],
                ];

                if ($file['type'] === 'dir') {
                    $dirs[] = $info;
                } else {
                    $files[] = $info;
                }
            }

            return array_merge($dirs, $files);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function addFile($file)
    {
        try {
            return $this->client->createFile(urldecode(ltrim($file, '/')));
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function addFolder($dir)
    {
        try {
            return $this->client->createDir(urldecode(ltrim($dir, '/')));
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function getFileContent($file)
    {
        try {
            return $this->client->getFileContent($file);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function updateFileContent($file, $content)
    {
        try {
            $this->client->removeFile($file);
            return $this->client->createFile($file, $content);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException("Unable to edit file [$file].");
        }
    }

    public function remove($files)
    {
        try {
            foreach ($files as $file) {
                if ($this->client->isDir($file)) {
                    $this->client->removeDir($file);
                } else {
                    $this->client->removeFile($file);
                }
            }
            return true;
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function rename($file, $newName)
    {
        try {
            return $this->client->rename($file, $newName);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function getDirectoryTree()
    {
        try {
            return $this->client->listDirDetails('/', true, FtpClient::DIR_TYPE);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function move($file, $newPath)
    {
        try {
            return $this->client->move(ltrim($file, '/'), $newPath);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function permissions($file, $permissions)
    {
        try {
            return $this->client->setPermissions($file, $permissions);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
        }
    }

    public function download($file)
    {
        try {
            $fileContent = $this->getFileContent($file);
            return (new HttpResponse($fileContent))
                ->addHeader('Content-Type', 'application/octet-stream')
                ->addHeader('Content-Disposition', 'attachment; filename=' . basename($file));
        } catch (\Exception $ex) {
            throw new FtpClientAdapterException("Failed to download file $file.");
        }
    }

    public function upload($filePath, $remotePath, $resume)
    {
        try {
            return $this->client->upload($filePath, $remotePath, $resume);
        } catch (FtpClientException $ex) {
            throw new FtpClientAdapterException($this->normalizeExceptionMessage($ex->getMessage()));
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
     * @param string $message
     *
     * @return string
     */
    protected function normalizeExceptionMessage($message)
    {
        return preg_replace('/([\[\w\]]+)\s-\s/i', '', $message);
    }
}
