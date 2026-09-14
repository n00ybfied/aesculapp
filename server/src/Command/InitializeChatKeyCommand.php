<?php
declare(strict_types=1);
namespace App\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\PrivateDataKeyPath;

#[AsCommand(name: 'app:private-data:initialize-key', description: 'Create the private-data encryption key once; never overwrite an existing key.', aliases: ['app:chat:initialize-key'])]
final class InitializeChatKeyCommand extends Command {
    public function __construct(private readonly PrivateDataKeyPath $keyPath) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $path = $this->keyPath->get();
        if (is_file($path)) { $output->writeln('Existing key preserved.'); return Command::SUCCESS; }
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) { return Command::FAILURE; }
        $handle = fopen($path, 'x');
        if ($handle === false) { return Command::FAILURE; }
        chmod($path, 0600);
        $key = base64_encode(random_bytes(32));
        $written = fwrite($handle, $key);
        fclose($handle);
        if ($written !== strlen($key)) { throw new \RuntimeException('Could not write chat key.'); }
        $output->writeln('Private-data key created. Back it up separately and keep it out of deployments and Git.');
        return Command::SUCCESS;
    }
}
