<?php

namespace App\Services\Command\Files;

use App\Entity\Activity;
use App\Entity\ActivityFile;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class removeUnusedAtivityFiles extends Command
{
    const UPLOAD_ACTIVITY_FILE_DIR = "/src/uploads/files/Activity";


    /**
     * @var string Name of the command
     */
    protected static $defaultName = 'activityFiles:clear';

    /**
     * @var string Root directory of the project
     */
    private string $rootDir;

    private EntityManagerInterface $entityManager;

    public function __construct(KernelInterface $kernel, EntityManagerInterface $entityManager)
    {
        $this->rootDir = $kernel->getProjectDir();
        $this->entityManager = $entityManager;
        parent::__construct(null);
    }

    public function configure()
    {
        $this
            ->setDescription("Clear the temporary files.")
            ->setHelp("Execute this command to purge the activity files directory of the application.");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {

        $filesList = scandir($this->rootDir.self::UPLOAD_ACTIVITY_FILE_DIR);

        $activitiesFilesFormBDD = $this->entityManager->getRepository(ActivityFile::class)->findAll();

        $activityFileListName = new ArrayCollection();
        foreach($activitiesFilesFormBDD as $activityFile){
            $activityFileListName->add($activityFile->getUniqId(). '_'. $activityFile->getFilename());
        }

        $table = new Table($output);
        $table->setHeaders(["File", "Deleted", "Error"]);
        $tableContent = [];

        /*
         * //todo recup la liste des fichiers sur le serveurs
         * recup la liste des activity avec fichiers dans la bdd
         * filtrer les fichiers présents dans la listes des activitys
         *
         * delete ce qu'il reste dans la liste
         */
        foreach ($filesList as $file) {
            if ($file === '.' OR $file === '..') {
                continue;
            }
            if(!$activityFileListName->contains($file)){
                try {
                    if (unlink($this->rootDir.self::UPLOAD_ACTIVITY_FILE_DIR."/".$file)) {
                        $tableContent[] = [$file, "\u{2705}", 'None'];
                    } else {
                        $tableContent[] = [$file, "\u{274C}", 'Unknown'];
                    }
                } catch (Exception $e) {
                    $tableContent[] = [$file, "\u{274C}", $e->getMessage()];
                }
            }
        }

        $table
            ->setRows($tableContent)
            ->render();

        return 0;
    }
}