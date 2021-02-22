<?php


namespace App\Service\Command\Config;


use App\Entity\GlobalPropertyAttribute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class gpaTypeMime extends Command
{
    protected static $defaultName = 'gpa:type_mime';

    private EntityManagerInterface $entityManager;

    /**
     * gpaTypeMime constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct(null);
    }

    protected function configure()
    {
        //todo help
        $this
            ->setDescription('Add MIME types to allow uploading files')
            ->setHelp('')
            ->addArgument("mime", InputArgument::OPTIONAL, "A Mime type you want to allow like text/csv... ", null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $mimeQ = new Question("Enter the type mime you want to allow : ");
        $quitQ = new Question("Add another type mime ? y/n : ");

        $helper = $this->getHelper('question');

        //set variable
        $mime = $input->getArgument("mime");

        $gpaRepo = $this->entityManager->getRepository(GlobalPropertyAttribute::class);

        try{
            $gpaTab = $gpaRepo->findBy(["propertyKey" => "mime.type.allowed"]);
        }catch (\Exception $e) {
            $output->writeln("An error occurred : " . $e->getMessage());
            return Command::FAILURE;
        }

        do {
            $mime = $helper->ask($input, $output, $mimeQ);
            try {
                $isExist = false;
                foreach ($gpaTab as $gpa) {
                    if ($gpa->getPropertyValue() === $mime) {
                        $output->writeln("This type Mime already exist in GPA");
                        $isExist = true;
                    }
                }
                if(!$isExist){
                    try{
                        $gpa = New GlobalPropertyAttribute();
                        $gpa->setPropertyKey("mime.type.allowed")
                            ->setDescription("Type MIME allowed for uplaoding files")
                            ->setScope("GLOBAL")
                            ->setPropertyValue($mime);
                        $this->entityManager->persist($gpa);
                        $this->entityManager->flush();
                        $gpaTab[] = $gpa;
                        $output->writeln("The MIME type has been successfully updated");
                    } catch (\Exception $e) {
                        $output->writeln("An error occurred : " . $e->getMessage());
                        return Command::FAILURE;
                    }
                }
            } catch (\Exception $e) {
                $output->writeln("An error occurred : " . $e->getMessage());
                return Command::FAILURE;
            }
            $quit = $helper->ask($input, $output, $quitQ);
        }while($quit == "y");

        $output->writeln(self::$defaultName ." [OFF]");
        return Command::SUCCESS;
    }
}