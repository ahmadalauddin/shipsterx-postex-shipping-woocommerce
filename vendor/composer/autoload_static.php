<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb7670d90fa803400168312d1b096854c
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PostEx\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PostEx\\' => 
        array (
            0 => __DIR__ . '/../..' . '/tests',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb7670d90fa803400168312d1b096854c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb7670d90fa803400168312d1b096854c::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb7670d90fa803400168312d1b096854c::$classMap;

        }, null, ClassLoader::class);
    }
}
