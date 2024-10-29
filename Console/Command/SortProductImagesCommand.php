<?php

namespace Musicworld\AutomaticImageAssign\Console\Command;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SortProductImagesCommand extends Command
{
    protected $state;
    protected $productRepository;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;
    protected $sortOrderBuilder;
    protected $filesystem;

    public function __construct(
        State $state,
        ProductRepository $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        SortOrderBuilder $sortOrderBuilder,
        Filesystem $filesystem
    ) {
        $this->state = $state;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->filesystem = $filesystem;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('musicworld:sort-product-images')
            ->setDescription('Sort product images, moving videos to the end if they have the smallest position');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode('adminhtml');
        $batchSize = 1000;
        $currentPage = 1;
        $totalProcessedProducts = 0;

        do {
            $sortOrder = $this->sortOrderBuilder
                ->setField('entity_id')
                ->setDirection(SortOrder::SORT_DESC)
                ->create();

            $statusFilter = $this->filterBuilder
                ->setField('status')
                ->setConditionType('eq')
                ->setValue(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder
                ->setPageSize($batchSize)
                ->setCurrentPage($currentPage)
                ->addSortOrder($sortOrder)
                ->addFilters([$statusFilter])
                ->create();

            $productCollection = $this->productRepository->getList($searchCriteria)->getItems();

            if (empty($productCollection)) {
                break;
            }

            $processedCount = 0;
            foreach ($productCollection as $product) {
                $images = $product->getMediaGalleryEntries();
                $hasVideo = false;
                $hasChanges = false; // Flag für Änderungen

                // Überprüfe, ob das Produkt ein Video hat
                foreach ($images as $image) {
                    if ($image->getMediaType() === 'external-video') {
                        $hasVideo = true;
                        break; // Produkt hat ein Video, es kann bearbeitet werden
                    }
                }

                if (!$hasVideo) {
                    continue; // Überspringe Produkte ohne Videos
                }

                // Verarbeite das Produkt, wenn es ein Video hat
                $videoImage = null;
                $sortedImages = [];
                $minPosition = null;

                // Bestimme die kleinste Positions-ID
                foreach ($images as $image) {
                    if ($minPosition === null || $image->getPosition() < $minPosition) {
                        $minPosition = $image->getPosition();
                    }
                }

                // Zuerst alle Bilder durchlaufen und das Video finden
                foreach ($images as $image) {
                    if ($image->getMediaType() === 'external-video' && $image->getPosition() === $minPosition) {
                        $videoImage = $image; // Video hat die kleinste Position, speichern
                    } else {
                        $sortedImages[] = $image; // Füge normale Bilder zur Liste hinzu
                    }
                }

                // Wenn das Video die kleinste Position hat, verschiebe es ans Ende
                if ($videoImage !== null) {
                    $sortedImages[] = $videoImage;
                    $output->writeln("Moved video with smallest position to end for Product ID: " . $product->getId());
                    $hasChanges = true; // Setze das Flag auf true, wenn Änderungen vorgenommen wurden
                }

                // Positionen aktualisieren
                foreach ($sortedImages as $key => $sortedImage) {
                    $sortedImage->setPosition($key + 1); // Setze die neue Position basierend auf der Reihenfolge in $sortedImages
                }

                // Setze die sortierten Bilder zurück ins Produkt
                $product->setMediaGalleryEntries($sortedImages);

                // Ausgabe der sortierten Liste
                $output->writeln("Sorted image list for Product ID: " . $product->getId());
                foreach ($sortedImages as $sortedImage) {
                    $output->writeln(
                        "- " . $sortedImage->getFile()
                        . " (Type: " . $sortedImage->getMediaType()
                        . ", New Position: " . $sortedImage->getPosition() . ")"
                    );
                }

                $output->writeln("----------");

                // Nur speichern, wenn Änderungen vorgenommen wurden
                if ($hasChanges) {
                    $this->productRepository->save($product);
                    $output->writeln("Product ID: " . $product->getId() . " saved with changes.");
                }

                $processedCount++;
            }

            $totalProcessedProducts += $processedCount;
            $output->writeln("Processed {$processedCount} products on page {$currentPage}.");

            $currentPage++;
        } while (count($productCollection) == $batchSize);

        $output->writeln("<info>Total processed products: {$totalProcessedProducts}</info>");
        return Cli::RETURN_SUCCESS;
    }
}
