<?php

namespace App\Apps\Employees\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Utilities\AuthenticationManager;
use App\Utilities\ChartManager;
use App\Utilities\CsrfManager;
use App\Utilities\EmployeeManager;
use App\Utilities\FlashManager;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        if ($redirect = AuthenticationManager::guard()) {
            return $redirect;
        }

        $customerId = AuthenticationManager::customerId();

        return $this->view('employees/index.twig', [
            'employees' => EmployeeManager::listWithInfoForCustomer($customerId),
            'department_breakdown' => ChartManager::pieWithLeaders(EmployeeManager::departmentBreakdown($customerId)),
            'city_breakdown' => ChartManager::pieWithLeaders(EmployeeManager::cityBreakdown($customerId)),
        ]);
    }

    public function show(Request $request, string $uuid): Response
    {
        if ($redirect = AuthenticationManager::guard()) {
            return $redirect;
        }

        $employee = EmployeeManager::profileForCustomer(AuthenticationManager::customerId(), $uuid);

        if ($employee === null) {
            return $this->notFound();
        }

        $actingEmployee = AuthenticationManager::currentEmployee();
        $canEditPhoto = $actingEmployee !== null && $actingEmployee->role === EmployeeManager::ROLE_ADMIN;

        return $this->view('employees/show.twig', ['employee' => $employee, 'can_edit_photo' => $canEditPhoto]);
    }

    public function showPhoto(Request $request, string $uuid): Response
    {
        if ($redirect = AuthenticationManager::guard()) {
            return $redirect;
        }

        $file = EmployeeManager::photoFile(AuthenticationManager::customerId(), $uuid);

        if ($file === null) {
            return $this->notFound();
        }

        // The URL doesn't change when a photo is replaced (same uuid,
        // new stored filename), so this can't be cached with a long
        // max-age without risking a stale photo after a re-upload.
        // "no-cache" still lets the browser skip re-downloading the
        // bytes on every page load — it just revalidates via ETag first,
        // which is a cheap 304 when nothing changed.
        $etag = '"' . md5($file['path'] . filemtime($file['path'])) . '"';

        if (($request->server['HTTP_IF_NONE_MATCH'] ?? null) === $etag) {
            return new Response('', 304, ['ETag' => $etag, 'Cache-Control' => 'private, no-cache']);
        }

        return new Response(file_get_contents($file['path']), 200, [
            'Content-Type' => $file['mime'],
            'ETag' => $etag,
            'Cache-Control' => 'private, no-cache',
        ]);
    }

    public function uploadPhoto(Request $request, string $uuid): Response
    {
        if ($redirect = AuthenticationManager::guard()) {
            return $redirect;
        }

        if (!CsrfManager::verify($request->body['csrf_token'] ?? null)) {
            FlashManager::add('Session has expired.', 'error');
            return $this->redirect('/employees/' . $uuid);
        }

        $actingEmployee = AuthenticationManager::currentEmployee();

        $result = EmployeeManager::updatePhoto(
            AuthenticationManager::customerId(),
            $uuid,
            $request->files['photo'] ?? [],
            $actingEmployee->role === EmployeeManager::ROLE_ADMIN,
        );

        FlashManager::add($result['success'] ? 'Employee photo updated.' : $result['error'], $result['success'] ? 'success' : 'error');

        return $this->redirect('/employees/' . $uuid);
    }
}
