<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $uid = session('usuario_id');
        $user = DB::table('usuarios')->find($uid);

        $cultivosActivos = DB::table('cultivos')->where('usuario_id', $uid)->where('estado', 'activo')->count();
        $primerDia = now()->startOfMonth()->toDateString();
        $ultimoDia = now()->endOfMonth()->toDateString();

        $gastosMes   = DB::table('gastos')->where('usuario_id', $uid)->whereBetween('fecha', [$primerDia, $ultimoDia])->sum('valor');
        $ingresosMes = DB::table('ingresos')->where('usuario_id', $uid)->whereBetween('fecha', [$primerDia, $ultimoDia])->sum('valor_total');
        $tareasPend  = DB::table('tareas')->where('usuario_id', $uid)->where('completada', 0)->where('fecha', '>=', now()->toDateString())->count();

        $tareasHoy = DB::table('tareas')
            ->where('usuario_id', $uid)->where('completada', 0)->whereDate('fecha', today())
            ->orderByRaw("FIELD(prioridad,'alta','media','baja')")->limit(3)->get();

        $recentCultivos = DB::table('cultivos')->where('usuario_id', $uid)
            ->orderBy('created_at', 'desc')->limit(3)->get();

        return view('pages.dashboard', compact(
            'user','cultivosActivos','gastosMes','ingresosMes','tareasPend','tareasHoy','recentCultivos'
        ));
    }
}
