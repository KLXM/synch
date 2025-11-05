<?php

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Console Command für die Synchronisation
 */
class synch_sync_command extends rex_console_command
{
    protected function configure(): void
    {
        $this
            ->setName('synch:sync')
            ->setDescription('Synchronisiert Module, Templates und Actions zwischen Dateisystem und Datenbank')
            ->addOption(
                'modules-only',
                'm', 
                InputOption::VALUE_NONE,
                'Synchronisiert nur Module'
            )
            ->addOption(
                'templates-only',
                't',
                InputOption::VALUE_NONE, 
                'Synchronisiert nur Templates'
            )
            ->addOption(
                'actions-only',
                'a',
                InputOption::VALUE_NONE, 
                'Synchronisiert nur Actions'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Führt einen Test-Lauf aus ohne Änderungen zu schreiben'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Synch - Moderne Key-basierte Synchronisation</info>');
        $output->writeln('=====================================');
        
        $modulesOnly = $input->getOption('modules-only');
        $templatesOnly = $input->getOption('templates-only');
        $actionsOnly = $input->getOption('actions-only');
        $dryRun = $input->getOption('dry-run');
        
        if ($dryRun) {
            $output->writeln('<comment>DRY RUN - Keine Änderungen werden geschrieben</comment>');
        }
        
        $success = true;
        
        try {
            // Module synchronisieren
            if (!$templatesOnly && !$actionsOnly) {
                $output->writeln('<info>Synchronisiere Module...</info>');
                
                if (!$dryRun) {
                    $moduleSync = new synch_module_synchronizer();
                    $moduleSync->sync();
                }
                
                $output->writeln('<info>✓ Module synchronisiert</info>');
            }
            
            // Templates synchronisieren
            if (!$modulesOnly && !$actionsOnly) {
                $output->writeln('<info>Synchronisiere Templates...</info>');
                
                if (!$dryRun) {
                    $templateSync = new synch_template_synchronizer();
                    $templateSync->sync();
                }
                
                $output->writeln('<info>✓ Templates synchronisiert</info>');
            }

            // Actions synchronisieren
            if (!$modulesOnly && !$templatesOnly) {
                $output->writeln('<info>Synchronisiere Actions...</info>');
                
                if (!$dryRun) {
                    $actionSync = new synch_action_synchronizer();
                    $actionSync->sync();
                }
                
                $output->writeln('<info>✓ Actions synchronisiert</info>');
            }
            
            $output->writeln('');
            $output->writeln('<info>✓ Synchronisation erfolgreich abgeschlossen</info>');
            
        } catch (Exception $e) {
            $output->writeln('<error>✗ Fehler bei der Synchronisation: ' . $e->getMessage() . '</error>');
            $success = false;
        }
        
        return $success ? 0 : 1;
    }
}