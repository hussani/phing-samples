<?php

require_once "phing/Task.php";
require_once 'phing/tasks/ext/ssh/ScpTask.php';


/**
* ScpTask with filelist support
*/
class ScpTaskFilelist extends ScpTask
{
    protected $filelists = array();

    /**
     * Nested creator, creates a FileSet for this task
     *
     * @return FileSet The created fileset object
     */
    public function createFileList() {
        $num = array_push($this->filelists, new FileList());
        return $this->filelists[$num-1];
    }

    public function main()
    {
        $p = $this->getProject();

        if (!function_exists('ssh2_connect')) {
            throw new BuildException("To use ScpTask, you need to install the PHP SSH2 extension.");
        }

        if ($this->file == "" && empty($this->filesets) && empty($this->filelists)) {
            throw new BuildException("Missing either a nested fileset, nested filelist or attribute 'file'");
        }

        if ($this->host == "" || $this->username == "") {
            throw new BuildException("Attribute 'host' and 'username' must be set");
        }

        $methods = !empty($this->methods) ? $this->methods->toArray($p) : array();
        $this->connection = ssh2_connect($this->host, $this->port, $methods);
        if (!$this->connection) {
            throw new BuildException("Could not establish connection to " . $this->username . "@" . $this->host . ":" . $this->port . "!");
        }

        $could_auth = null;
        if ( $this->pubkeyfile ) {
            $could_auth = ssh2_auth_pubkey_file($this->connection, $this->username, $this->pubkeyfile, $this->privkeyfile, $this->privkeyfilepassphrase);
        } else {
            $could_auth = ssh2_auth_password($this->connection, $this->username, $this->password);
        }
        if (!$could_auth) {
            throw new BuildException("Could not authenticate connection!");
        }

        // prepare sftp resource
        if ($this->autocreate) {
            $this->sftp = ssh2_sftp($this->connection);
        }

        if ($this->file != "") {
            $this->copyFile($this->file, basename($this->file));
        } elseif ($this->filelists) {
            foreach ($this->filelists as $fl) {
                $files = $fl->getFiles($this->project);
                $dir = $fl->getDir($this->project);
                foreach ($files as $file) {
                    $path = $dir.DIRECTORY_SEPARATOR.$file;

                    // Translate any Windows paths
                    $this->copyFile($path, strtr($file, '\\', '/'));
                }
            }
        } else {
            if ($this->fetch) {
                throw new BuildException("Unable to use filesets to retrieve files from remote server");
            }

            foreach($this->filesets as $fs) {
                $ds = $fs->getDirectoryScanner($this->project);
                $files = $ds->getIncludedFiles();
                $dir = $fs->getDir($this->project)->getPath();
                foreach($files as $file) {
                    $path = $dir.DIRECTORY_SEPARATOR.$file;

                    // Translate any Windows paths
                    $this->copyFile($path, strtr($file, '\\', '/'));
                }
            }
        }

        $this->log("Copied " . $this->counter . " file(s) " . ($this->fetch ? "from" : "to") . " '" . $this->host . "'");

        // explicitly close ssh connection
        @ssh2_exec($this->connection, 'exit');
    }
}