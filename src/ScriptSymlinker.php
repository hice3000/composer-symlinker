<?php

namespace Hice\Symlinker;

use Composer\IO\IOInterface;
use Composer\Script\Event;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class ScriptSymlinker
{
    public static function symlink(Event $event)
    {
        $event->getIO()->write('Checking Symlinks ...');
        $symlinks = static::findDefinitions($event);
        
        $config = static::loadConfig($event);
        
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        
        foreach ($symlinks as $origin => $target) {
            static::store(
                $vendorDir.$origin,
                $vendorDir.$target,
                $event->getIO(),
                $config['mirror_on_failure'],
                $config['always_mirror']
             );
        }
    }
    
    public static function store($origin, $target, $io = null, $mirrorOnFailure = true, $alwaysMirror = false)
    {
        if ($io instanceof IOInterface) {
            if (!$alwaysMirror) {
                $io->write(sprintf(
                       'Symlinking: %1$s => %2$s', $origin, $target
                ));
            } else if ($alwaysMirror) {
                $io->write(sprintf(
                       'Mirroring: %1$s => %2$s', $origin, $target
                ));
            }
        }
        
        $fs = new Filesystem();
        
        if ($alwaysMirror == 'true') {
            $fs->mirror($origin, $target);
            return;
        }
        
        try {
            $fs->symlink($origin, $target);
        } catch (IOException $ex) { // if symlinking failed, e.g. because cli is not run as admin
            if ($mirrorOnFailure == 'true') {
                if ($io instanceof IOInterface) {
                    $io->write('Symlinking failed, trying to mirror ...');
                }
                $fs->mirror($origin, $target);
            } else { // if mirroring is disabled
                if ($io instanceof IOInterface) {
                    $io->write('Symlinking failed and mirroring was disabled.');
                }
            }
        }
    }
    
    /*
     * @param \Composer\Script\Event $event
     * 
     * @return arary
     */
    public static function findDefinitions(Event $event)
    {
        $extra = $event->getComposer()->getPackage()->getExtra();
        $root = $extra['facemanga-symlinks']; // the key in "extra"
        $symlinks = array();
        $fs = new Filesystem();
        
        foreach ($root['symlinks'] as $origin => $target) {
            $symlinks[$origin] = $target; // add symlink to array
        }
        
        return $symlinks;
    }
    
    public static function loadConfig(Event $event)
    {
        $extra = $event->getComposer()->getPackage()->getExtra();
        $root = $extra['facemanga-symlinks']; // the key in "extra"
        $config = array();
        
        $config['always_mirror'] = (array_key_exists('always-mirror', $root)) ? $root['always-mirror'] : false;
        $config['mirror_on_failure'] = (array_key_exists('mirror-on-failure', $root)) ? $root['mirror-on-failure'] : true;
        
        return $config;
    }
}