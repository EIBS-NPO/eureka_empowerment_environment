<?php


namespace App\Services\Command\Config;


use App\Entity\GlobalPropertyAttribute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class initApplication extends Command
{
    protected static $defaultName = 'gpa:init';

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
        $this
            ->setDescription('init application deployment');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $gpaRepo = $this->entityManager->getRepository(GlobalPropertyAttribute::class);

        try{
         //   $gpa = $gpaRepo->findBy(["propertyKey" => "mime.type.allowed"]);
            $gpaForbiddenString = New GlobalPropertyAttribute();
            $gpaForbiddenString->setPropertyKey("security.fields.forbiddenstrings")
                ->setDescription("forgoten string XSS")
                ->setScope("GLOBAL")
                ->setValue('<script>');
            $this->entityManager->persist($gpaForbiddenString);

            $gpaFileAllowed = new GlobalPropertyAttribute();
            $gpaFileAllowed->setPropertyKey("mime.type.allowed")
                ->setDescription("allowed file mimes")
                ->setScope("GLOBAL")
                ->setValue('application/pdf,image/jpeg');
            $this->entityManager->persist($gpaFileAllowed);

            $this->entityManager->flush();

        }catch (\Exception $e) {
            $output->writeln("An error occurred : " . $e->getMessage());
            return Command::FAILURE;
        }

        $output->writeln(self::$defaultName ." [OFF]");

        return Command::SUCCESS;
    }
}