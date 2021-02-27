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
            $gpa = $gpaRepo->findBy(["propertyKey" => "mime.type.allowed"]);
            if(empty($gpa)){
                $gpa = New GlobalPropertyAttribute();
                $gpa->setPropertyKey("mime.type.allowed")
                    ->setDescription("Type MIME allowed for uplaoding files")
                    ->setScope("GLOBAL");
            }else{
                $gpa = $gpa[0];
            }

        }catch (\Exception $e) {
            $output->writeln("An error occurred : " . $e->getMessage());
            return Command::FAILURE;
        }

        do {
            $mime = $helper->ask($input, $output, $mimeQ);
            if($gpa->hasValue($mime)){
                $output->writeln("This type Mime already add to the list or exist in GPA");
            }
            else{
                $gpa->setValue($mime);
                $output->writeln($mime . " add for update. ");
            }
            $quit = $helper->ask($input, $output, $quitQ);
        }while($quit == "y");

        if(count($gpa->getPropertyValue())>0){
            try{
                if($gpa->getId() === null ){
                    $this->entityManager->persist($gpa);
                }
                $this->entityManager->flush();
                $output->writeln("GPA type Mime successfully updated. ");

            } catch (\Exception $e) {
                $output->writeln("An error occurred : " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $output->writeln(self::$defaultName ." [OFF]");

        return Command::SUCCESS;
    }
}