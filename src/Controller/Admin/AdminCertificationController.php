<?php

namespace App\Controller\Admin;

use App\Repository\CertificationRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminCertificationController extends AbstractController
{
    #[Route('/admin/certifications/{id}/download', name: 'certification_download', methods: ['GET'])]
    public function download(int $id, CertificationRepository $repo): Response
    {
        $cert = $repo->find($id);
        if (!$cert) {
            throw $this->createNotFoundException('Certification introuvable.');
        }

        $html = $this->renderView('user/certification_pdf.html.twig', [
            'cert' => $cert,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $rawCode = $cert->getCertificateCode() ?? (string) $cert->getId();
        $code = preg_replace('/[^A-Za-z0-9_-]/', '-', $rawCode);

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificat-' . $code . '.pdf"',
        ]);
    }
}