<?php

namespace App\Services\Files;

use App\Models\UploadedFile;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecureUploadService
{
    public function storeDesign(HttpUploadedFile $file, ?int $userId = null, ?int $adminId = null): UploadedFile
    {
        return $this->store($file, 'design', false, $userId, $adminId);
    }

    public function storeImage(HttpUploadedFile $file, string $purpose, ?int $userId = null, ?int $adminId = null): UploadedFile
    {
        return $this->store($file, $purpose, true, $userId, $adminId);
    }

    private function store(HttpUploadedFile $file, string $purpose, bool $public, ?int $userId, ?int $adminId): UploadedFile
    {
        if (!$file->isValid()) throw ValidationException::withMessages(['file'=>'الملف المرفوع غير صالح.']);
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        $isDesign = $purpose === 'design';
        $extensions = config($isDesign ? 'uploads.design_extensions' : 'uploads.image_extensions', []);
        $mimes = config($isDesign ? 'uploads.design_mimes' : 'uploads.image_mimes', []);
        $maxKb = (int) config($isDesign ? 'uploads.max_design_kb' : 'uploads.max_image_kb', 5120);
        if (!in_array($extension, $extensions, true) || !in_array($mime, $mimes, true)) {
            throw ValidationException::withMessages(['file'=>'نوع الملف غير مسموح.']);
        }
        if ($file->getSize() > ($maxKb * 1024)) throw ValidationException::withMessages(['file'=>'حجم الملف أكبر من الحد المسموح.']);
        // Never trust the client filename for a filesystem path.
        $uuid = (string) Str::uuid();
        $storedName = $uuid . '.' . $extension;
        $disk = $public ? config('uploads.public_disk','public') : config('uploads.private_disk','local');
        $directory = ($public ? 'uploads' : 'secure_uploads') . '/' . date('Y/m') . '/' . preg_replace('/[^a-z0-9_-]+/i', '-', $purpose);
        $path = $file->storeAs($directory, $storedName, $disk);
        if (!$path) throw ValidationException::withMessages(['file'=>'تعذر حفظ الملف.']);
        $real = $file->getRealPath();
        return UploadedFile::create([
            'uuid'=>$uuid,'user_id'=>$userId,'admin_id'=>$adminId,'disk'=>$disk,'path'=>$path,
            'original_name'=>basename((string) $file->getClientOriginalName()),'stored_name'=>$storedName,
            'extension'=>$extension,'mime_type'=>$mime,'size'=>$file->getSize(),'sha256'=>$real ? hash_file('sha256',$real) : null,
            'purpose'=>$purpose,'is_public'=>$public,'metadata'=>null,
        ]);
    }

    public function delete(UploadedFile $file): bool
    {
        $deleted = Storage::disk($file->disk)->delete($file->path);
        if ($deleted || !Storage::disk($file->disk)->exists($file->path)) $file->delete();
        return $deleted;
    }

    public function response(UploadedFile $file, bool $download = false)
    {
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);
        $absolute = Storage::disk($file->disk)->path($file->path);
        return $download
            ? response()->download($absolute, $file->original_name, ['Content-Type'=>$file->mime_type])
            : response()->file($absolute, ['Content-Type'=>$file->mime_type,'X-Content-Type-Options'=>'nosniff']);
    }
}
