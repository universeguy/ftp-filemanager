<?php

namespace FTPApp\Controllers;

use FTPApp\Controllers\Controller;
use FTPApp\Http\HttpRequest;
use FTPApp\Modules\FtpClientAdapter;

abstract class FilemanagerControllerAbstract extends Controller {

    /** @var FtpClientAdapter */
    protected $ftpClientAdapter;

    public function __construct(HttpRequest $request)
    {
        parent::__construct($request);
        $this->retrieveFromSession();       
        $this->prepare();
    }

    /**
     * Stores the ftpClientAdapter as a session variable.
     * 
     * @return void
     */
    protected function storeInSession()
    {
        $this->session()->start();
        $this->sessionStorage()->setVariable('ftpClientAdapter', $this->ftpClientAdapter);
    }

    /**
     * Sets the FtpClient adapter restored from the session variable. 
     * 
     * @return void
     */
    private function retrieveFromSession()
    {
        $this->session()->start();
        $this->ftpClientAdapter = $this->sessionStorage()->getVariable('ftpClientAdapter');
    }

    /**
     * @return void
     */
    private function prepare()
    {
        $this->ftpClientAdapter->openConnection();
    }
}