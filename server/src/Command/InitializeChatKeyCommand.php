<?php
declare(strict_types=1);
namespace App\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:chat:initialize-key', description: 'Create the chat encryption key once; never overwrite an existing key.')]
final class InitializeChatKeyCommand extends Command {
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $path = $_ENV['CHAT_KEY_FILE'] ?? (getenv('CHAT_KEY_FILE') ?: $this->projectDir.'/var/private/chat.key');
        if (is_file($path)) { $output->writeln('Existing key preserved.'); return Command::SUCCESS; }
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) { return Command::FAILURE; }
        $handle = fopen($path, 'x');
        if ($handle === false) { return Command::FAILURE; }
        chmod($path, 0600);
        $key = base64_encode(random_bytes(32));
        $written = fwrite($handle, $key);
        fclose($handle);
        if ($written !== strlen($key)) { throw new \RuntimeException('Could not write chat key.'); }
        $output->writeln('Chat key created. Back it up separately and keep it out of deployments and Git.');
        return Command::SUCCESS;
    }
}
