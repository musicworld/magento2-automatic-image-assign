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

class CleanMissingImagesCommand extends Command
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
        SortOrderBuilder $sortOrderBuilder, // SortOrderBuilder wird injiziert
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
        $this->setName('musicworld:clean-missing-images')
            ->setDescription('Clean up products with missing base, thumbnail, or small images')
            ->addOption(
                'save', // Name des Parameters
                null, // Shortcut, falls nicht erforderlich, auf null setzen
                \Symfony\Component\Console\Input\InputOption::VALUE_NONE, // Parameter ist ein Flag, kein Wert
                'Save products after cleaning up missing images' // Beschreibung des Parameters
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode('adminhtml');
        $batchSize = 1000; // Anzahl der Produkte pro Batch
        $maxChanges = 100; // Maximale Anzahl der zu ändernden Produkte
        $changedCount = 0; // Zähler für geänderte Produkte

        // Überprüfen, ob der --save Parameter gesetzt ist
        $shouldSave = $input->getOption('save');

        // Pfad zum Medienverzeichnis dynamisch ermitteln
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();

        $currentPage = 1;

        do {
            // Erstelle ein SortOrder-Objekt für die Sortierung
            $sortOrder = $this->sortOrderBuilder
                ->setField('entity_id')
                ->setDirection(SortOrder::SORT_DESC)
                ->create();

            // Füge einen Filter für den Produktstatus hinzu
            $statusFilter = $this->filterBuilder
                ->setField('status')
                ->setConditionType('eq')
                ->setValue(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                ->create();


            // Erstelle das SearchCriteria-Objekt für die aktuelle Seite
            $searchCriteria = $this->searchCriteriaBuilder
                ->setPageSize($batchSize)
                ->setCurrentPage($currentPage)
                ->addSortOrder($sortOrder)
                ->addFilters([
                    $this->filterBuilder->setField('image')->setConditionType('neq')->setValue('no_selection')->create(),
                    $this->filterBuilder->setField('thumbnail')->setConditionType('neq')->setValue('no_selection')->create(),
                    $this->filterBuilder->setField('small_image')->setConditionType('neq')->setValue('no_selection')->create(),
                    $statusFilter])
                ->create();

            // Holen der Produktliste mit Bildverweisen
            $productCollection = $this->productRepository->getList($searchCriteria)->getItems();

            // Überprüfen der tatsächlichen Anzahl der abgerufenen Produkte
            $output->writeln("Processing batch of " . count($productCollection) . " products on page " . $currentPage);

            if (empty($productCollection)) {
                break;
            }

            foreach ($productCollection as $product) {
                $hasMissingImages = false;

                // Prüfen, ob die verknüpften Bilder existieren
                $imagePaths = [
                    'image' => $product->getImage(),
                    'small_image' => $product->getSmallImage(),
                    'thumbnail' => $product->getThumbnail()
                ];

                foreach ($imagePaths as $imageType => $imagePath) {
                    $fullImagePath = $mediaDirectory . 'catalog/product' . $imagePath;
                    if (!file_exists($fullImagePath) && $imagePath !== 'no_selection') {
                        // Setze das Bildattribut auf 'no_selection', falls das Bild fehlt
                        $product->setData($imageType, 'no_selection');
                        $hasMissingImages = true;
                        $output->writeln("Missing image for product ID: " . $product->getId() . " ($imageType: $imagePath)");
                    }
                }

                if ($hasMissingImages) {
                    if ($shouldSave) {
                        $this->productRepository->save($product);
                        $output->writeln("Product ID: " . $product->getId() . " cleaned and saved.");
                    } else {
                        $output->writeln("Product ID: " . $product->getId() . " would be cleaned (run with --save to apply changes).");
                    }
                    $changedCount++;

                    // Überprüfen, ob die maximale Anzahl geänderter Produkte erreicht ist
                    if ($changedCount >= $maxChanges) {
                        $output->writeln('<info>Maximum number of 100 changed products reached. Exiting.</info>');
                        return Cli::RETURN_SUCCESS;
                    }
                }
            }

            $currentPage++;
        } while (count($productCollection) == $batchSize);

        $output->writeln('<info>Missing images cleanup process completed.</info>');
        return Cli::RETURN_SUCCESS;
    }
}
