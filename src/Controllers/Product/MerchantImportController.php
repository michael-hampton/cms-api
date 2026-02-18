<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Enums\ImportType;
use App\Framework\Database\Database;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Imports\CsvParser;
use App\Imports\ImportOptions;
use App\Imports\MerchantImportService;
use App\Imports\MerchantOfferImport;
use App\Imports\MerchantProductImport;
use App\Imports\MerchantVoucherImport;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\MerchantProductRepository;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Repositories\Vouchers\VoucherRepository;
use App\Requests\MerchantImportRequest;
use Exception;

class MerchantImportController extends Controller
{
    public function __construct(
        private readonly MerchantImportService      $importService,
        private readonly Database                   $database,
        private readonly CsvParser                  $csvParser,
        private readonly VoucherRepository          $voucherRepository,
        private readonly ProductOfferRepository     $offerRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly MerchantProductRepository  $merchantProductRepository,
        private readonly RewardDefinitionRepository $rewardDefinitionRepository
    )
    {
        parent::__construct();
    }

    public function import(Request $request, int $merchantId): JsonResponse
    {
        try {
            $importRequest = new MerchantImportRequest($request->all(), $request->files());
            $data = $importRequest->validated();

            /** @var ImportType $type */
            $type = $data['type'];
            $updateExisting = $data['update_existing'];
            $file = $data['file'];
            $siteId = SiteContext::getId();


            $filePath = $this->importService->upload($file, $merchantId);
            $importer = $this->buildImporter($type);

            $importOptions = new ImportOptions($merchantId, $siteId, $updateExisting);
            $importer->setOptions($importOptions);

            $result = $this->importService->import($importer, $filePath);

            return $this->resourceResponse([
                'message' => 'Import completed.',
                'imported' => $result->importedCount(),
                'skipped' => $result->skippedCount(),
                'skipped_rows' => $result->skippedRows(),
            ], 200);

        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    private function buildImporter(
        ImportType $type,
    ): \App\Imports\BaseMerchantImport
    {
        return match ($type) {
            ImportType::Voucher => new MerchantVoucherImport(
                $this->database,
                $this->csvParser,
                $this->voucherRepository,
                $this->rewardDefinitionRepository
            ),
            ImportType::Offer => new MerchantOfferImport(
                $this->database,
                $this->csvParser,
                $this->offerRepository,
                $this->merchantProductRepository,
                $this->rewardDefinitionRepository
            ),
            ImportType::Product => new MerchantProductImport(
                $this->database,
                $this->csvParser,
                $this->productRepository,
                $this->merchantProductRepository
            ),
        };
    }
}