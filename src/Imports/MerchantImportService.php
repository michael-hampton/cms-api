<?php

namespace App\Imports;

use App\DTO\ImportResult;
use App\Framework\FileUpload\FileUpload;
use App\Framework\Http\UploadedFile;

class MerchantImportService
{
    public function __construct(
        private readonly MerchantVoucherImport $voucherImport,
        private readonly MerchantOfferImport   $offerImport,
        private readonly MerchantProductImport $productImport
    )
    {
    }

    public function upload(UploadedFile $file, int $merchantId): string
    {
        $upload = new FileUpload($file, 'uploads/imports');
        $upload->setAllowedExtensions(['csv', 'txt']);

        return $upload->store("merchant_{$merchantId}");
    }

    public function import(BaseMerchantImport $importer, string $filePath): ImportResult
    {
        return $importer->import($filePath);
    }
}