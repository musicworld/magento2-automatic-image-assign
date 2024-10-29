<?php
namespace Musicworld\AutomaticImageAssign\Console\Command;

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AssignImagesCommand extends Command
{
    protected $state;
    protected $productRepository;
    protected $mediaConfig;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;
    protected $filesystem;
    protected $sortOrderBuilder;

    public function __construct(
        State $state,
        ProductRepository $productRepository,
        Config $mediaConfig,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        Filesystem $filesystem,
        SortOrderBuilder $sortOrderBuilder // SortOrderBuilder injizieren
    ) {
        $this->state = $state;
        $this->productRepository = $productRepository;
        $this->mediaConfig = $mediaConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->filesystem = $filesystem;
        $this->sortOrderBuilder = $sortOrderBuilder;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('musicworld:assign-images')
            ->setDescription('Assign base, thumbnail, and small images to products without these images')
            ->addOption(
                'save', // Name des Parameters
                null, // Shortcut, falls nicht erforderlich, auf null setzen
                \Symfony\Component\Console\Input\InputOption::VALUE_NONE, // Parameter ist ein Flag, kein Wert
                'Save products after assigning images' // Beschreibung des Parameters
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode('adminhtml');

        // Überprüfen, ob der --save Parameter gesetzt ist
        $shouldSave = $input->getOption('save');

        // Pfad zum Medienverzeichnis dynamisch ermitteln
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();

        // Erstelle SortOrder, um die neuesten Produkte zuerst zu holen
        $sortOrder = $this->sortOrderBuilder
            ->setField('entity_id')
            ->setDirection(SortOrder::SORT_DESC)
            ->create();

        // Erstelle Filter für jedes Bild-Attribut
        $baseImageFilter = $this->filterBuilder
            ->setField('image')
            ->setValue('no_selection')
            ->setConditionType('eq')
            ->create();
        $thumbnailFilter = $this->filterBuilder
            ->setField('thumbnail')
            ->setValue('no_selection')
            ->setConditionType('eq')
            ->create();
        $smallImageFilter = $this->filterBuilder
            ->setField('small_image')
            ->setValue('no_selection')
            ->setConditionType('eq')
            ->create();

        // Erstelle eine Filtergruppe für die OR-Bedingung
        $filterGroup = new FilterGroup();
        $filterGroup->setFilters([$baseImageFilter, $thumbnailFilter, $smallImageFilter]);

        // Erstelle das SearchCriteria-Objekt
        $searchCriteria = $this->searchCriteriaBuilder
            ->setFilterGroups([$filterGroup])
            ->addSortOrder($sortOrder) // SortOrder hinzufügen
            ->create();

        // Zähler für geänderte Produkte
        $changedCount = 0;
        $batchSize = 100; // Maximale Anzahl der zu ändernden Produkte

        // Holen der Produktliste mit dem SearchCriteria
        $productCollection = $this->productRepository->getList($searchCriteria)->getItems();

        foreach ($productCollection as $product) {
            // Prüfen, ob das Produkt kein Basisbild, kein kleines Bild oder kein Thumbnail zugewiesen hat
            if (
                !$product->getImage() || $product->getImage() == 'no_selection' ||
                !$product->getSmallImage() || $product->getSmallImage() == 'no_selection' ||
                !$product->getThumbnail() || $product->getThumbnail() == 'no_selection'
            ) {
                // Holen des ersten Bildes aus der Mediengalerie
                $galleryImages = $product->getMediaGalleryImages();
                if (count($galleryImages)) {
                    $firstImage = $galleryImages->getFirstItem();
                    $imagePath = $firstImage->getFile(); // Pfad des ersten Bildes holen
                    $fullImagePath = $mediaDirectory . 'catalog/product' . $imagePath;
                    if (file_exists($fullImagePath) && $imagePath) {
                        // Weisen des ersten Bildes als Basisbild, kleines Bild und Thumbnail zu
                        $product->setImage($imagePath);
                        $product->setSmallImage($imagePath);
                        $product->setThumbnail($imagePath);
                        $output->writeln("Assigned first image to product ID: " . $product->getId());

                        if ($shouldSave) {
                            $this->productRepository->save($product);
                            $output->writeln("Product ID: " . $product->getId() . " saved.");
                        }

                        $changedCount++;
                    } else {
                        $output->writeln("Image file does not exist for product ID: " . $product->getId());
                    }
                } else {
                    $output->writeln("No images found for product ID: " . $product->getId());
                }

                // Beende das Skript, wenn 100 Produkte geändert wurden
                if ($changedCount >= $batchSize) {
                    $output->writeln('<info>Maximum number of 100 changed products reached. Exiting.</info>');
                    return Cli::RETURN_SUCCESS;
                }
            }
        }

        $output->writeln('<info>Images assigned successfully where applicable.</info>');
        return Cli::RETURN_SUCCESS;
    }
}
