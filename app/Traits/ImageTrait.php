<?php

namespace App\Traits;

trait ImageTrait
{
    public function uploadImg($image, string $filename, string $folder): string
    {
        $image->move(public_path('images/'.$folder), $filename);

        return 'images/'.$folder.'/'.$filename;
    }
}
