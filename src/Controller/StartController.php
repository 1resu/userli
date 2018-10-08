<?php

namespace App\Controller;

use App\Creator\VoucherCreator;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Form\Model\PasswordChange;
use App\Form\Model\VoucherCreate;
use App\Form\PasswordChangeType;
use App\Form\VoucherCreateType;
use App\Handler\VoucherHandler;
use App\Helper\PasswordUpdater;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class StartController.
 */
class StartController extends Controller
{
    /**
     * @var PasswordUpdater
     */
    private $passwordUpdater;
    /**
     * @var VoucherHandler
     */
    private $voucherHandler;
    /**
     * @var VoucherCreator
     */
    private $voucherCreator;

    /**
     * StartController constructor.
     *
     * @param PasswordUpdater $passwordUpdater
     * @param VoucherHandler  $voucherHandler
     * @param VoucherCreator  $voucherCreator
     */
    public function __construct(PasswordUpdater $passwordUpdater, VoucherHandler $voucherHandler, VoucherCreator $voucherCreator)
    {
        $this->passwordUpdater = $passwordUpdater;
        $this->voucherHandler = $voucherHandler;
        $this->voucherCreator = $voucherCreator;
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->render('Start/index_anonymous.html.twig');
        }

        /** @var User $user */
        $user = $this->getUser();

        $voucherCreateForm = $this->createForm(
            VoucherCreateType::class,
            new VoucherCreate(),
            [
                'action' => $this->generateUrl('index'),
                'method' => 'post',
            ]
        );

        $passwordChange = new PasswordChange();
        $passwordChangeForm = $this->createForm(
            PasswordChangeType::class,
            $passwordChange,
            [
                'action' => $this->generateUrl('index'),
                'method' => 'post',
            ]
        );

        if ('POST' === $request->getMethod()) {
            $voucherCreateForm->handleRequest($request);
            $passwordChangeForm->handleRequest($request);

            if ($voucherCreateForm->isSubmitted() && $voucherCreateForm->isValid()) {
                $this->createVoucher($request, $user);
            } elseif ($passwordChangeForm->isSubmitted() && $passwordChangeForm->isValid()) {
                $this->changePassword($request, $user, $passwordChange->newPassword);
            }
        }

        $vouchers = $this->voucherHandler->getVouchersByUser($user);

        return $this->render(
            'Start/index.html.twig', [
                'user' => $user,
                'vouchers' => $vouchers,
                'voucher_form' => $voucherCreateForm->createView(),
                'password_form' => $passwordChangeForm->createView(),
            ]
        );
    }

    /**
     * @param Request $request
     * @param User    $user
     */
    private function createVoucher(Request $request, User $user)
    {
        if ($this->isGranted('ROLE_SUPPORT')) {
            try {
                $this->voucherCreator->create($user);

                $request->getSession()->getFlashBag()->add('success', 'flashes.voucher-creation-successful');
            } catch (ValidationException $e) {
                // Should not thrown
            }
        }
    }

    /**
     * @param Request $request
     * @param User    $user
     * @param string  $password
     */
    private function changePassword(Request $request, User $user, string $password)
    {
        $user->setPlainPassword($password);

        $this->passwordUpdater->updatePassword($user);

        $this->getDoctrine()->getManager()->flush();

        $request->getSession()->getFlashBag()->add('success', 'flashes.password-change-successful');
    }
}