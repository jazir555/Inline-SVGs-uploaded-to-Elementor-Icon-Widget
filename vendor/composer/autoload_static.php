<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit40742fb6ce8184cca6cb391a82fc3f04
{
    public static $prefixLengthsPsr4 = array (
        'e' => 
        array (
            'enshrined\\svgSanitize\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'enshrined\\svgSanitize\\' => 
        array (
            0 => __DIR__ . '/..' . '/enshrined/svg-sanitize/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit40742fb6ce8184cca6cb391a82fc3f04::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit40742fb6ce8184cca6cb391a82fc3f04::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit40742fb6ce8184cca6cb391a82fc3f04::$classMap;

        }, null, ClassLoader::class);
    }
}
