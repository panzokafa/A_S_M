<?php

namespace App\Http\Controllers\Admin;

use App\Asset;
use App\Http\Controllers\Controller;
use App\Http\Requests\MassDestroyStockRequest;
use App\Http\Requests\StoreStockRequest;
use App\Http\Requests\UpdateStockRequest;
use App\Team;
use App\User;
use App\Stock;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StockAlertController extends Controller
{


    public function index()
    {
        abort_if(Gate::denies('stock_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $dangerStock  = $this->dangerStocksByTeam();
        $transactions = $this->dailyTransactions();

        return view('emails.adminDailyReportEmail', compact('dangerStock', 'transactions'));
    }

    // public function index()
    // {
    //     abort_if(Gate::denies('stock_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

    //     $stocks = Stock::all();

    //     return view('admin.stocks.index', compact('stocks'));
    // }

    public function create()
    {
        abort_if(Gate::denies('stock_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $assets = Asset::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.stocks.create', compact('assets'));
    }

    public function store(StoreStockRequest $request)
    {
        $stock = Stock::create($request->all());

        return redirect()->route('admin.stocks.index');

    }

    public function edit(Stock $stock)
    {
        abort_if(Gate::denies('stock_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $assets = Asset::all()->pluck('name', 'id')->prepend(trans('global.pleaseSelect'), '');

        $stock->load('asset', 'team');

        return view('admin.stocks.edit', compact('assets', 'stock'));
    }

    public function update(UpdateStockRequest $request, Stock $stock)
    {
        $stock->update($request->all());

        return redirect()->route('admin.stocks.index');

    }

    public function show(Stock $stock)
    {
        abort_if(Gate::denies('stock_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $stock->load('asset.transactions.user.team');

        return view('admin.stocks.show', compact('stock'));
    }

    public function destroy(Stock $stock)
    {
        abort_if(Gate::denies('stock_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $stock->delete();

        return back();

    }

    public function massDestroy(MassDestroyStockRequest $request)
    {
        Stock::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);

    }

            /**
     * @return mixed
     */
    private function dailyTransactions()
    {
        //count sum of transactions grouped by team and asset
        $transactions = Transaction::select(['transactions.asset_id', 'transactions.team_id', DB::raw('sum(stock) as sum')])
            ->with(['team', 'asset'])
            ->groupBy(['team_id', 'asset_id'])
            ->join('teams', 'transactions.team_id', '=', 'teams.id')
            ->orderBy('teams.name')
            ->orderByDesc('sum')
            ->get();

        $stocks = Stock::all();

        //set current_stock for every transaction
        foreach ($transactions as $transaction) {
            $transaction->current_stock = $stocks->where('team_id', $transaction->team_id)
                ->where('asset_id', $transaction->asset_id)
                ->first()
                ->current_stock;
        }

        return $transactions;
    }

    /**
     * @return Builder[]|Collection
     */
    private function dangerStocksByTeam()
    {
        return Team::with(['stocks' => function ($q) {
            return $q->select(['stocks.*', 'assets.*'])
                ->join('assets', function ($join) {
                    $join->on('stocks.asset_id', '=', 'assets.id');
                })
                ->where('assets.danger_level', '>', 0)
                ->whereRaw('stocks.current_stock < assets.danger_level');
        }])
            ->orderBy('name')
            ->get();
    }

}
