<?php

declare(strict_types=1);

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
final class Versions
{
    public const ROOT_PACKAGE_NAME = 'topthink/think';
    public const VERSIONS          = array (
  'clue/stream-filter' => 'v1.4.1@5a58cc30a8bd6a4eb8f856adf61dd3e013f53f71',
  'filp/whoops' => '2.3.1@bc0fd11bc455cc20ee4b5edabc63ebbf859324c7',
  'guzzlehttp/psr7' => '1.5.2@9f83dded91781a01c63574e387eaa769be769115',
  'http-interop/http-factory-guzzle' => '1.0.0@34861658efb9899a6618cef03de46e2a52c80fc0',
  'jean85/pretty-package-versions' => '1.2@75c7effcf3f77501d0e0caa75111aff4daa0dd48',
  'mix/delayer-client' => 'v1.0.3@46c42356c865a2108c14c265af49023938e0c58e',
  'ocramius/package-versions' => '1.4.0@a4d4b60d0e60da2487bd21a2c6ac089f85570dbb',
  'paragonie/random_compat' => 'v9.99.99@84b4dfb120c6f9b4ff7b3685f9b8f1aa365a0c95',
  'php-http/client-common' => '2.0.0@2b8aa3c4910afc21146a9c8f96adb266e869517a',
  'php-http/curl-client' => '2.0.0@e7a2a5ebcce1ff7d75eaf02b7c85634a6fac00da',
  'php-http/discovery' => '1.6.1@684855f2c2e9d0a61868b8f8d6bd0295c8a4b651',
  'php-http/httplug' => 'v2.0.0@b3842537338c949f2469557ef4ad4bdc47b58603',
  'php-http/message' => '1.7.2@b159ffe570dffd335e22ef0b91a946eacb182fa1',
  'php-http/message-factory' => 'v1.0.2@a478cb11f66a6ac48d8954216cfed9aa06a501a1',
  'php-http/promise' => 'v1.0.0@dc494cdc9d7160b9a09bd5573272195242ce7980',
  'psr/http-client' => '1.0.0@496a823ef742b632934724bf769560c2a5c7c44e',
  'psr/http-factory' => '1.0.1@12ac7fcd07e5b077433f5f2bee95b3a771bf61be',
  'psr/http-message' => '1.0.1@f6561bf28d520154e4b0ec72be95418abe6d9363',
  'psr/log' => '1.1.0@6c001f1daafa3a3ac1d8ff69ee4db8e799a654dd',
  'ralouphie/getallheaders' => '2.0.5@5601c8a83fbba7ef674a7369456d12f1e0d0eafa',
  'ramsey/uuid' => '3.8.0@d09ea80159c1929d75b3f9c60504d613aeb4a1e3',
  'sentry/sdk' => '2.0.3@91c36aec83e4c1c5801b64ef4927b78a5aa8ce7f',
  'sentry/sentry' => '2.1.1@8e27e6c5fcf6f01fc2e5235dd14cc0b2b347d793',
  'symfony/options-resolver' => 'v4.3.1@914e0edcb7cd0c9f494bc023b1d47534f4542332',
  'symfony/polyfill-ctype' => 'v1.11.0@82ebae02209c21113908c229e9883c419720738a',
  'topthink/framework' => 'v5.1.34@7c8cd1ea2bd4683a6ef46af415334260c6e2cc15',
  'topthink/think-installer' => 'v2.0.0@f5400a12c60e513911aef41fe443fa6920952675',
  'zendframework/zend-diactoros' => '2.1.2@37bf68b428850ee26ed7c3be6c26236dd95a95f1',
  'topthink/think' => 'dev-master@0dd77b9bc062416aab79a3bf37af85222fdc87b7',
);

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException If a version cannot be located.
     */
    public static function getVersion(string $packageName) : string
    {
        if (isset(self::VERSIONS[$packageName])) {
            return self::VERSIONS[$packageName];
        }

        throw new \OutOfBoundsException(
            'Required package "' . $packageName . '" is not installed: cannot detect its version'
        );
    }
}