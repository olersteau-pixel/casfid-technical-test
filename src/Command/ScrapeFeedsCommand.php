<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\FeedsScraperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scrape-feeds',
    description: 'Scrapea las noticias principales de El País y El Mundo',
)]
final class ScrapeFeedsCommand extends Command
{
    public function __construct(
        private readonly FeedsScraperService $feedsScraperService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Número de noticias a obtener por fuente',
                5
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $io->title('Scraping de Noticias');
        $io->info(sprintf('Obteniendo %d noticias de cada fuente...', $limit));

        try {
            $stats = $this->feedsScraperService->scrapeAllSources($limit);

            $io->success('¡Scraping completado exitosamente!');

            // Mostrar estadísticas generales
            $io->section('Resumen General');
            $io->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Noticias scrapeadas', $stats->total_scraped],
                    ['Noticias guardadas', $stats->total_saved],
                    ['Duplicados omitidos', $stats->total_duplicates],
                    ['Errores', $stats->total_errors],
                ]
            );

            // Mostrar estadísticas por fuente
            $io->section('Detalle por Fuente');
            foreach ($stats->sources as $source => $sourceStats) {
                if (isset($sourceStats->error)) {
                    $io->error(sprintf('%s: %s', $source, $sourceStats->error));
                } else {
                    $io->text(sprintf(
                        '<info>%s:</info> %d scrapeadas, %d guardadas, %d duplicados',
                        $source,
                        $sourceStats['scraped'],
                        $sourceStats['saved'],
                        $sourceStats['duplicates']
                    ));
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error durante el scraping: '.$e->getMessage());

            if ($output->isVerbose()) {
                $io->block($e->getTraceAsString(), 'TRACE', 'fg=white;bg=red', ' ', true);
            }

            return Command::FAILURE;
        }
    }
}
