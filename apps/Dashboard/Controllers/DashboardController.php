<?php

namespace App\Apps\Dashboard\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Utilities\AuthenticationManager;
use App\Utilities\DashboardManager;

class DashboardController extends Controller
{
    
    public function index(Request $request): Response
    {

        if ($redirect = AuthenticationManager::guard()) {

            return $redirect;
        
            }

        return $this->view('dashboard/index.twig', DashboardManager::overviewForCustomer(
            AuthenticationManager::customerId(),
            AuthenticationManager::userId(),
        ));

    }

}
