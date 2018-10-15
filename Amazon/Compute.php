<?php

namespace CloudDoctor\Amazon;

use CloudDoctor\CloudDoctor;
use CloudDoctor\Common\ComputeGroup;
use CloudDoctor\Exceptions\CloudDoctorException;
use CloudDoctor\Interfaces\ComputeInterface;
use GuzzleHttp\Exception\ClientException;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use phpseclib\Net\SSH2;

class Compute extends \CloudDoctor\Common\Compute implements ComputeInterface
{
    
}