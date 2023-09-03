<?php

namespace App\Http\Controllers;

use App\Services\SubsiteService;
use App\Services\DatabaseService;
use App\Services\MigrateTablesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MigrationController extends Controller
{
    public function index()
    {
        $databases = DatabaseService::getAssociativeDatabaseList();

        return view('migrator', [
            'databases' => $databases,
        ]);
    }

    public function getSubsites(SubsiteService $service, Request $request)
    {
        $database = request('database');
        if (! $database) {
            return response()->json(['error' => 'No Database']);
        }
        DatabaseService::setDb($database);
        $currentUrl = DatabaseService::getInverseDatabaseList()[$database];

        $subsites = $service->getActiveBlogsForSelect();

        return response()->json(['subsites' => $subsites, 'currentUrl' => $currentUrl]);
    }

    public function migration(MigrateTablesService $service, Request $request)
    {
        $databaseFrom = request('databaseFrom');
        $databaseTo = request('databaseTo');
        $fromValues = request('from');

        $results = null;
        if ($databaseFrom && $databaseTo && $fromValues) {
            $blogIds = explode(',', $fromValues);
            $results = $service->migrateMultiple($databaseFrom, $databaseTo, $blogIds);
        }

        return response()->json(['results' => $results]);
    }
}
