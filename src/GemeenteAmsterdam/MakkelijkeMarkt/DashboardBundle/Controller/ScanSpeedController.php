<?php
/*
 *  Copyright (C) 2017 X Gemeente
 *                     X Amsterdam
 *                     X Onderzoek, Informatie en Statistiek
 *
 *  This Source Code Form is subject to the terms of the Mozilla Public
 *  License, v. 2.0. If a copy of the MPL was not distributed with this
 *  file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

namespace GemeenteAmsterdam\MakkelijkeMarkt\DashboardBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use GemeenteAmsterdam\MakkelijkeMarkt\DashboardBundle\Form\Type\ScanSpeedSelectorType;
use Doctrine\Common\Collections\ArrayCollection;

class ScanSpeedController extends Controller
{
    /**
     * @Route("/scan-speed")
     * @Template()
     * @Security("has_role('ROLE_ADMIN') or has_role('ROLE_SENIOR')")
     */
    public function indexAction(Request $request)
    {
        /* @var $api \GemeenteAmsterdam\MakkelijkeMarkt\DashboardBundle\Service\MarktApi */
        $api = $this->get('markt_api');
        $accounts = $api->getAccounts()['results'];
        $markten = $api->getMarkten()['results'];

        $marktId   = $request->query->get('markt');
        $accountId = $request->query->get('account');
        $datum     = $request->query->get('datum');
        $pauze     = $request->query->get('pauze');

        if (!is_null($marktId) && !is_null($accountId) && !is_null($datum) && !is_null($pauze)) {
            $settings = (object) [
                'marktId' => null,
                'dag' => new \DateTime(),
                'accountId' => null,
                'pauseDetect' => 60*5
            ];

            $dagvergunningen = $api->getDagvergunningen([
                'marktId'   => $marktId,
                'dag'       => $datum,
                'accountId' => $accountId,
                'doorgehaald' => -1
            ], 0, 10000);

            $periods = new ArrayCollection();
            $currentPeriod = null;
            $prevDagvergunning = null;
            $fnNewPeriod = function ($start) use ($periods) { $new = (object) ['start' => $start, 'end' => 0, 'duration' => 0, 'scans' => 0, 'avgTimePerScan' => 0, 'avgScansPerHour' => 0]; $periods->add($new); return $new; };
            $dagvergunningen['results'] = array_reverse($dagvergunningen['results']);
            foreach ($dagvergunningen['results'] as $dagvergunning) {
                if ($currentPeriod === null || $prevDagvergunning === null) {
                    $currentPeriod = $fnNewPeriod(strtotime($dagvergunning->registratieDatumtijd));
                    $currentPeriod->scans ++;
                    $prevDagvergunning = $dagvergunning;
                } else if ((strtotime($dagvergunning->registratieDatumtijd) - strtotime($prevDagvergunning->registratieDatumtijd)) > $settings->pauseDetect) {
                    $currentPeriod->end = strtotime($prevDagvergunning->registratieDatumtijd);
                    $currentPeriod = $fnNewPeriod(strtotime($dagvergunning->registratieDatumtijd));
                    $currentPeriod->scans ++;
                    $prevDagvergunning = $dagvergunning;
                } else {
                    $currentPeriod->scans ++;
                    $prevDagvergunning = $dagvergunning;
                }
            }
            if ($currentPeriod !== null) {
                $currentPeriod->end = strtotime($prevDagvergunning->registratieDatumtijd);
            }
            foreach ($periods as $period) {
                $period->duration = $period->end - $period->start;
                if ($period->duration === 0) {
                    $period->duration = 1;
                    $period->avgTimePerScan = -1;
                    $period->avgScansPerHour = -1;
                } else {
                    $period->avgTimePerScan = $period->duration / $period->scans;
                    $period->avgScansPerHour = (60 * 60) / $period->avgTimePerScan;
                }

            }

            return [
                'accounts'  => $accounts,
                'accountId' => $accountId,
                'markten'   => $markten,
                'marktId'   => $marktId,
                'datum'     => $datum,
                'pauze'     => $pauze,
                'periods'   => $periods
            ];

        }

        $pauze = null === $pauze ? 300 : $pauze;

        return [
            'accounts' => $accounts,
            'markten'  => $markten,
            'pauze'    => $pauze
        ];
    }
}
