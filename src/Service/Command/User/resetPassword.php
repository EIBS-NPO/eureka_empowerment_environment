<?php


namespace App\Service\Command\User;


use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class resetPassword extends Command
{
    protected static $defaultName = 'user:resetPassword';

    private EntityManagerInterface $entityManager;

    private UserPasswordEncoderInterface $passwordEncoder;

    private ValidatorInterface $validator;

    /**
     * Create constructor.
     * @param EntityManagerInterface $entityManager
     * @param UserPasswordEncoderInterface $passwordEncoder
     */
    public function __construct(EntityManagerInterface $entityManager, UserPasswordEncoderInterface $passwordEncoder, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->validator = $validator;
        parent::__construct(null);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription("Reset user's Password.")
            ->setHelp("This command allows you to reset a user'spassword...")
            //Add optional argument for passing the username
            ->addArgument("email", InputArgument::OPTIONAL, "The email of the user to reset his password", null)
     //       ->addArgument("password", InputArgument::OPTIONAL, "The password of the user", null)
            ->addArgument("password", InputArgument::OPTIONAL, "The new password", null)
            ->addArgument("confirmPassword", InputArgument::OPTIONAL, "The new password repeated for control", null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $emailQ = new Question("Enter the email of the user : ");
        $passwordQ = new Question("Enter the user's password : ");
        $confirmPasswordQ = new Question("Re-enter the user's password for confirmation : ");

        $helper = $this->getHelper('question');

        //Set variable
        $email = $input->getArgument("email");
        $password = $input->getArgument("password");
        $confirmPassword = $input->getArgument("confirmPassword");

        $uRepo = $this->entityManager->getRepository(User::class);
        do{
            $email = $helper->ask($input, $output, $emailQ);
            try {
                $user = $uRepo->findBy(["email" => $email]);
                if($user == null){
                    $output->writeln("The user was'nt found.");
                }else {
                    $user = $user[0];
                    $output->writeln("The user was successfully found.");
                }
            } catch (\Exception $e) {
                $output->writeln("An error occurred : ".$e->getMessage());
                return Command::FAILURE;
            }
        }while($user == null);

        do{
            $password = $helper->ask($input, $output, $passwordQ);

            //password validation
            $violations = $this->validator->validatePropertyValue($user, "password", $password);
            if(count($violations) > 0 ){
                $violationsList ="";
                foreach($violations as $violation){
                    $violationsList = $violationsList . $violation->getPropertyPath() ." => " . $violation->getMessage() . PHP_EOL;
                }
                $output->writeln($violationsList);
            }
            if(count($violations) == 0){
                $confirmPassword = $helper->ask($input, $output, $confirmPasswordQ);
                if($password !== $confirmPassword){
                    $output->writeln("Confirmation password isn't match, please try again.");
                }
            }
        }while($password !== $confirmPassword);

        try {
            $user->setPassword($this->passwordEncoder->encodePassword($user, $password));
            $user->setFirstname("Bobo");
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $output->writeln("An error occurred : ".$e->getMessage());
            return Command::FAILURE;
        }

        $output->writeln("The password's user was successfully updated.");
        return Command::SUCCESS;
    }
}