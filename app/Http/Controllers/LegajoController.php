<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LegajoController extends Controller
{
    private const NC_ROOT  = 'files/Proveedores habituales';
    private const LEGAJO   = 'Legajo técnico';

    public function index()
    {
        return view('legajo');
    }

    public function proveedores()
    {
        $root   = self::NC_ROOT;
        $legajo = self::LEGAJO;

        $proveedores = DB::connection('nextcloud')
            ->table('oc_filecache as f')
            ->join('oc_mimetypes as mt', 'mt.id', '=', 'f.mimetype')
            ->selectRaw("DISTINCT split_part(f.path,'/',3) as proveedor")
            ->where('f.path', 'like', "$root/%")
            ->whereRaw("split_part(f.path,'/',4) = ?", [$legajo])
            ->where('mt.mimetype', 'httpd/unix-directory')
            ->whereRaw("split_part(f.path,'/',3) <> ''")
            ->orderBy('proveedor')
            ->pluck('proveedor');

        return response()->json($proveedores);
    }

    public function generarQr(Request $request)
    {
        $proveedor = trim($request->input('proveedor', ''));

        if ($proveedor === '') {
            return response()->json(['error' => 'Seleccioná un proveedor.'], 422);
        }

        $folderPath = '/Proveedores habituales/' . $proveedor . '/' . self::LEGAJO;
        $ncUrl      = rtrim(config('services.nextcloud.url'), '/');
        $ncUser     = config('services.nextcloud.user');
        $ncPass     = config('services.nextcloud.pass');

        $headers = [
            'OCS-APIREQUEST' => 'true',
            'Accept'         => 'application/json',
        ];

        // 1. Buscar un share público existente para esta carpeta
        $res = Http::withBasicAuth($ncUser, $ncPass)
            ->withHeaders($headers)
            ->get("$ncUrl/ocs/v2.php/apps/files_sharing/api/v1/shares", [
                'path'     => $folderPath,
                'reshares' => false,
            ]);

        if ($res->successful()) {
            $shares = $res->json('ocs.data') ?? [];
            foreach ($shares as $share) {
                // shareType 3 = link público
                if (($share['share_type'] ?? null) === 3) {
                    return response()->json(['url' => "$ncUrl/s/{$share['token']}"]);
                }
            }
        }

        // 2. Crear un share de solo lectura sin vencimiento
        $create = Http::withBasicAuth($ncUser, $ncPass)
            ->withHeaders($headers)
            ->post("$ncUrl/ocs/v2.php/apps/files_sharing/api/v1/shares", [
                'path'        => $folderPath,
                'shareType'   => 3,  // link público
                'permissions' => 1,  // solo lectura
            ]);

        if (! $create->successful()) {
            Log::error('LegajoController: error al crear share en Nextcloud', [
                'status' => $create->status(),
                'body'   => $create->body(),
            ]);
            return response()->json(['error' => 'No se pudo crear el share en Nextcloud.'], 500);
        }

        $token = $create->json('ocs.data.token');

        if (! $token) {
            Log::error('LegajoController: token vacío en respuesta de Nextcloud', [
                'body' => $create->body(),
            ]);
            return response()->json(['error' => 'Respuesta inesperada de Nextcloud.'], 500);
        }

        return response()->json(['url' => "$ncUrl/s/$token"]);
    }
}
