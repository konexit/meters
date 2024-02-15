<?php

namespace App\ThirdParty;

trait LogTrait
{
    public function deleteOldLog($minCount = 7)
    {
        $count = 0;
        $excludeFiles = array('.', '..', 'index.html');
        $path = FCPATH . '../writable/logs/';
        $files = opendir($path);
        while (($file = readdir($files)) !== false) {
            if (in_array($file, $excludeFiles)) continue;
            $count++;
            if ($count < $minCount) continue;
            if (filectime($path . $file) <= time() - 14 * 24 * 60 * 60) {
                unlink($path . $file);
            }
        }
        closedir($files);
    }
}
