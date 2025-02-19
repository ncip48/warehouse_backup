<?php

namespace App\Http\Controllers;

use App\Models\DetailPo;
use App\Models\DetailPR;
use App\Models\Purchase_Order;
use App\Models\PurchaseRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $search = $request->q;
        if (Session::has('selected_warehouse_id')) {
            $warehouse_id = Session::get('selected_warehouse_id');
        } else {
            $warehouse_id = DB::table('warehouse')->first()->warehouse_id;
        }

        $purchases = Purchase_Order::select('purchase_order.*', 'vendor.nama as vendor_name', 'keproyekan.nama_proyek as proyek_name', 'purchase_request.no_pr as pr_no')
            ->where('purchase_order.tipe', "0")
            ->join('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->leftjoin('purchase_request', 'purchase_request.id', '=', 'purchase_order.pr_id')
            ->paginate(50);
        $vendors = DB::table('vendor')->get();
        $proyeks = DB::table('keproyekan')->get();


        if ($search) {
            $purchases = Purchase_Order::where('no_po', 'LIKE', "%$search%")->paginate(50);
        }

        if ($request->format == "json") {
            $purchases = Purchase_Order::where("warehouse_id", $warehouse_id)->get();

            return response()->json($purchases);
        } else {
            $prs = PurchaseRequest::all();
            return view('purchase_order.purchase_order', compact('purchases', 'vendors', 'proyeks', 'prs'));
        }
    }

    public function showPOPL(Request $request)
    {
        $search = $request->q;
        if (Session::has('selected_warehouse_id')) {
            $warehouse_id = Session::get('selected_warehouse_id');
        } else {
            $warehouse_id = DB::table('warehouse')->first()->warehouse_id;
        }

        $purchases = Purchase_Order::select('purchase_order.*', 'keproyekan.nama_proyek as proyek_name', 'purchase_request.no_pr as pr_no')
            ->where('purchase_order.tipe', '1')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->leftjoin('purchase_request', 'purchase_request.id', '=', 'purchase_order.pr_id')
            ->paginate(50);
        // $vendors = DB::table('vendor')->get();
        $proyeks = DB::table('keproyekan')->get();


        if ($search) {
            $purchases = Purchase_Order::where('no_po', 'LIKE', "%$search%")->paginate(50);
        }

        if ($request->format == "json") {
            $purchases = Purchase_Order::where("warehouse_id", $warehouse_id)->get();

            return response()->json($purchases);
        } else {
            $prs = PurchaseRequest::all();
            return view('purchase_order.po_pl', compact('purchases', 'proyeks', 'prs'));
        }
    }

    public function indexApps(Request $request)
    {
        $search = $request->q;

        $purchases = Purchase_Order::select('purchase_order.*', 'vendor.nama as vendor_name', 'keproyekan.nama_proyek as proyek_name', 'purchase_request.no_pr as pr_no')
            ->join('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->leftjoin('purchase_request', 'purchase_request.id', '=', 'purchase_order.pr_id')
            ->paginate(50);
        $vendors = DB::table('vendor')->get();
        $proyeks = DB::table('keproyekan')->get();

        if ($search) {
            $purchases = Purchase_Order::where('no_po', 'LIKE', "%$search%")->paginate(50);
        }

        if ($request->format == "json") {
            $purchases = Purchase_Order::all();

            return response()->json($purchases);
        } else {
            return view('home.apps.logistik.purchase_order', compact('purchases', 'vendors', 'proyeks'));
        }
    }

    public function getDetailPo(Request $request)
    {
        $id = $request->id;
        $po = Purchase_Order::select('purchase_order.*', 'vendor.nama as nama_vendor', 'keproyekan.nama_proyek as nama_proyek')
            ->join('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->where('purchase_order.id', $id)
            ->first();
        $po->details = DetailPo::where('detail_po.id_po', $id)
            ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
            ->select('detail_pr.*', 'detail_po.id as id_detail_po', 'detail_po.harga as harga_per_unit', 'detail_po.mata_uang as mata_uang', 'detail_po.vat as vat', 'detail_po.batas_akhir as batas')
            ->get();
        return response()->json([
            'po' => $po
        ]);
    }
    public function getDetailPOPL(Request $request)
    {
        $id = $request->id;
        $po = Purchase_Order::select('purchase_order.*', 'keproyekan.nama_proyek as nama_proyek')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->where('purchase_order.id', $id)
            ->first();
        $po->details = DetailPo::where('detail_po.id_po', $po->id)
            ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
            ->select('detail_pr.*', 'detail_po.id as id_detail_po', 'detail_po.harga as harga_per_unit', 'detail_po.mata_uang as mata_uang', 'detail_po.vat as vat', 'detail_po.batas_akhir as batas')
            ->get();

        return response()->json([
            'po' => $po
        ]);
        // dd($po);
    }

    public function detailPrSave(Request $request)
    {
        $id_po = $request->id_po;
        $id_detail_po = $request->id;
        $batas = $request->batas;
        $harga_per_unit = $request->harga_per_unit;
        $mata_uang = $request->mata_uang;
        $vat = $request->vat;

        DetailPo::where('id', $id_detail_po)->update([
            'batas_akhir' => $batas,
            'harga' => $harga_per_unit,
            'mata_uang' => $mata_uang,
            'vat' => $vat,
        ]);

        $po = Purchase_Order::select('purchase_order.*', 'vendor.nama as nama_vendor', 'keproyekan.nama_proyek as nama_proyek')
            ->leftjoin('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->where('purchase_order.id', $id_po)
            ->first();
        $po->details = DetailPo::where('detail_po.id_po', $po->id)
            ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
            ->select('detail_pr.*', 'detail_po.id as id_detail_po', 'detail_po.harga as harga_per_unit', 'detail_po.mata_uang as mata_uang', 'detail_po.vat as vat', 'detail_po.batas_akhir as batas')
            ->get();
        return response()->json([
            'po' => $po
        ]);
    }

    public function destroyDetailPo(Request $request)
    {
        // dd($request->all());
        $id = $request->id;
        $id_po = $request->id_po;

        $delete_detail_po = DetailPo::where('id', $id)->delete();
        $id_detailpr = $request->id_detail_pr;
        $delete_detail_pr = DetailPR::where('id', $id_detailpr)->update([
            'id_po' => null
        ]);
        

        if ($delete_detail_po && $delete_detail_pr) {
            $po = Purchase_Order::select('purchase_order.*', 'vendor.nama as nama_vendor', 'keproyekan.nama_proyek as nama_proyek')
                ->leftjoin('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
                ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
                ->where('purchase_order.id', $id_po)
                ->first();

            $po->details = DetailPo::where('detail_po.id_po', $po->id)
                ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
                ->select(
                    'detail_pr.*',
                    'detail_po.id as id_detail_po',
                    'detail_po.harga as harga_per_unit',
                    'detail_po.mata_uang as mata_uang',
                    'detail_po.vat as vat',
                    'detail_po.batas_akhir as batas'
                )
                ->get();
            return response()->json([
                'po' => $po
            ]);
        } else {
            return response()->json([
                'po' => null
            ]);
        }

        // $po = Purchase_Order::select('purchase_order.*', 'vendor.nama as nama_vendor', 'keproyekan.nama_proyek as nama_proyek')
        //     ->leftjoin('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
        //     ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
        //     ->where('purchase_order.id', $id_po)
        //     ->first();
        // $po->details = DetailPo::where('detail_po.id_po', $po->id)
        //     ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
        //     ->select('detail_pr.*', 'detail_po.id as id_detail_po', 'detail_po.harga as harga_per_unit', 'detail_po.mata_uang as mata_uang', 'detail_po.vat as vat', 'detail_po.batas_akhir as batas')
        //     ->get();
        // return response()->json([
        //     'po' => $po
        // ]);

    }

    public function test_pr(Request $request)
    {
        $id_po = $request->id_po;
        $po = Purchase_Order::select('purchase_order.*', 'vendor.nama as nama_vendor', 'keproyekan.nama_proyek as nama_proyek')
            ->leftjoin('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->where('purchase_order.id', $id_po)
            ->first();
        $po->details = DetailPo::where('detail_po.id_po', $po->id)
            ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
            ->select('detail_pr.*', 'detail_po.id as id_detail_po', 'detail_po.harga as harga_per_unit', 'detail_po.mata_uang as mata_uang', 'detail_po.vat as vat', 'detail_po.batas_akhir as batas')
            ->get();
        return response()->json([
            'po' => $po
        ]);
    }

    function tambahDetailPo(Request $request)
    {
        $id = $request->id_po;
        $selected = $request->selected;

        foreach ($selected as $key => $value) {
            $detail_pr = DetailPR::find($value);
            $detail_po = DetailPo::where('id_detail_pr', $value)->first();
            $update = DetailPR::where('id', $value)->update([
                'id_po' => $id,
                'status' => 2,
            ]);
            $add = DetailPo::create([
                'id_po' => $id,
                'id_pr' => $detail_pr->id_pr,
                'id_detail_pr' => $detail_pr->id,
            ]);
        }


        // Fetch the updated purchase order data
        $po = Purchase_Order::leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->leftjoin('purchase_request', 'purchase_request.id', '=', 'purchase_order.pr_id')
            ->leftjoin('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->select('purchase_order.*', 'keproyekan.*', 'vendor.*', 'purchase_request.*', 'purchase_order.id as id_po')
            ->where('purchase_order.id', $id)
            ->first();

        $po->details = DetailPo::where('detail_po.id_po', $po->id_po)
            ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
            ->select('detail_pr.*', 'detail_po.id as id_detail_po')
            ->get();

        return response()->json([
            'po' => $po
        ]);
    }

    public function tracking(Request $request)
    {
        $search = $request->q;

        if (Session::has('selected_warehouse_id')) {
            $warehouse_id = Session::get('selected_warehouse_id');
        } else {
            $warehouse_id = DB::table('warehouse')->first()->warehouse_id;
        }

        $requests = PurchaseRequest::select('purchase_request.*', 'keproyekan.nama_proyek as proyek_name')
            ->join('keproyekan', 'keproyekan.id', '=', 'purchase_request.proyek_id')
            ->paginate(50);

        $proyeks = DB::table('keproyekan')->get();

        if ($search) {
            $requests = PurchaseRequest::where('nama_proyek', 'LIKE', "%$search%")->paginate(50);
        }

        if ($request->format == "json") {
            $requests = PurchaseRequest::where("warehouse_id", $warehouse_id)->get();

            return response()->json($requests);
        } else {
            return view('admin.trackingpr', compact('requests', 'proyeks'));
        }
    }

    public function trackingwil(Request $request)
    {
        $search = $request->q;

        if (Session::has('selected_warehouse_id')) {
            $warehouse_id = Session::get('selected_warehouse_id');
        } else {
            $warehouse_id = DB::table('warehouse')->first()->warehouse_id;
        }

        $requests = PurchaseRequest::select('purchase_request.*', 'keproyekan.nama_proyek as proyek_name')
            ->join('keproyekan', 'keproyekan.id', '=', 'purchase_request.proyek_id')
            ->paginate(50);

        $proyeks = DB::table('keproyekan')->get();

        if ($search) {
            $requests = PurchaseRequest::where('nama_proyek', 'LIKE', "%$search%")->paginate(50);
        }

        if ($request->format == "json") {
            $requests = PurchaseRequest::where("warehouse_id", $warehouse_id)->get();

            return response()->json($requests);
        } else {
            return view('admin.trackingwil', compact('requests', 'proyeks'));
        }
    }

    public function updateDetailPo(Request $request)
    {
        $id = $request->id;
        $po = Purchase_Order::where('id', $id)->update([
            'no_po' => $request->no_po,
            'vendor_id' => $request->vendor_id,
            'tanggal_po' => $request->tanggal_po,
            'batas_po' => $request->batas_po,
            'incoterm' => $request->incoterm,
            'pr_id' => $request->pr_id,
            'ref_sph' => $request->ref_sph,
            'no_just' => $request->no_just,
            'no_nego' => $request->no_nego,
            'ref_po' => $request->ref_po,
            'term_pay' => $request->term_pay,
            'garansi' => $request->garansi,
            'proyek_id' => $request->proyek_id,
        ]);
        return response()->json([
            'po' => $po
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $purchase_order = $request->id;
        // $vendors = DB::table('vendor')->get();

        // if (Session::has('selected_warehouse_id')) {
        //     $warehouse_id = Session::get('selected_warehouse_id');
        // } else {
        //     $warehouse_id = DB::table('warehouse')->first()->warehouse_id;
        // }
        $request->validate(
            [
                'no_po' => 'required',
                'vendor_id' => 'required',
                'tanggal_po' => 'required',
                'batas_po' => 'required',
                'incoterm' => 'required',
                'pr_id' => 'required',
                'term_pay' => 'required',
                'proyek_id' => 'required',

            ],
            [
                'no_po.required' => 'No. PO harus diisi',
                'vendor_id.required' => 'Vendor harus diisi',
                'tanggal_po.required' => 'Tanggal PO harus diisi',
                'batas_po.required' => 'Batas Akhir PO harus diisi',
                'incoterm.required' => 'Incoterm harus diisi',
                'pr_id.required' => 'PR harus diisi',
                'term_pay.required' => 'Termin Pembayaran harus diisi',
                'proyek_id.required' => 'Proyek harus diisi',
            ]
        );

        if (empty($purchase_order)) {
            $tipe = 0; // 0 = PO biasa, 1 = PO PL
            $po = DB::table('purchase_order')->insertGetId([
                'no_po' => $request->no_po,
                'tipe' => $tipe,
                'vendor_id' => $request->vendor_id,
                'tanggal_po' => $request->tanggal_po,
                'batas_po' => $request->batas_po,
                'incoterm' => $request->incoterm,
                'ref_sph' => $request->ref_sph,
                'no_just' => $request->no_just,
                'no_nego' => $request->no_nego,
                'ref_po' => $request->ref_po,
                'term_pay' => $request->term_pay,
                'garansi' => $request->garansi,
                'proyek_id' => $request->proyek_id,
                'pr_id' => $request->pr_id,
                'catatan_vendor' => $request->catatan_vendor
            ]);

            // $prs = DetailPR::where('id_pr', $request->pr_id)->get();


            // foreach ($prs as $pr) {
            //     DetailPo::insert([
            //         'id_po' => $po,
            //         'id_pr' => $request->pr_id,
            //         'id_detail_pr' => $pr->id,
            //     ]);
            // }

            return redirect()->route('purchase_order.index')->with('success', 'Data PO berhasil ditambahkan');
        } else {
            DB::table('purchase_order')->where('id', $purchase_order)->update([
                'no_po' => $request->no_po,
                'vendor_id' => $request->vendor_id,
                // "tanggal_po"  => Carbon::now()->setTimezone('Asia/Jakarta'),
                // "batas_po" => Carbon::now()->setTimezone('Asia/Jakarta')
                'tanggal_po' => $request->tanggal_po,
                'batas_po' => $request->batas_po,
                'incoterm' => $request->incoterm,
                'pr_id' => $request->pr_id,
                'ref_sph' => $request->ref_sph,
                'no_just' => $request->no_just,
                'no_nego' => $request->no_nego,
                'ref_po' => $request->ref_po,
                'term_pay' => $request->term_pay,
                'garansi' => $request->garansi,
                'proyek_id' => $request->proyek_id,
                // 'catatan_vendor' => $request->catatan_vendor

            ]);
            return redirect()->route('purchase_order.index')->with('success', 'Data PO berhasil diubah');
        }
    }

    public function cetakPo(Request $request)
    {
        $id = $request->id_po;
        $po = Purchase_Order::select('purchase_order.*', 'vendor.nama as nama_vendor', 'vendor.alamat as alamat_vendor', 'vendor.telp as telp_vendor', 'vendor.email as email_vendor', 'vendor.fax as fax_vendor',  'keproyekan.nama_proyek as nama_proyek', 'purchase_request.no_pr as pr_no')
            ->leftjoin('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->leftjoin('purchase_request', 'purchase_request.id', '=', 'purchase_order.pr_id')
            ->where('purchase_order.id', $id)
            ->first();
        //  dd($po);
        $po->batas_po = Carbon::parse($po->batas_po)->isoFormat('D MMMM Y');
        $po->tanggal_po = Carbon::parse($po->tanggal_po)->isoFormat('D MMMM Y');
        $po->details = DetailPo::where('detail_po.id_po', $po->id)
            ->leftJoin('detail_pr', 'detail_pr.id', '=', 'detail_po.id_detail_pr')
            ->select('detail_pr.*', 'detail_po.id as id_detail_po', 'detail_po.harga as harga_per_unit', 'detail_po.mata_uang as mata_uang', 'detail_po.vat as vat', 'detail_po.batas_akhir as batas')
            ->get();
        $po->details = $po->details->map(function ($detail) {
            $detail->no_pr = PurchaseRequest::find($detail->id_pr)->no_pr;
            return $detail;
        });
        $po->details = $po->details->map(function ($detail) {
            $detail->no_just = DetailPR::find($detail->id)->no_just;
            return $detail;
        });
        $po->details = $po->details->map(function ($detail) {
            $detail->no_sph = DetailPR::find($detail->id)->no_sph;
            return $detail;
        });

        $po->details = $po->details->map(function ($detail) {
            $detail->no_nego = DetailPR::find($detail->id)->no_nego1;
            return $detail;
        });

        $po->no_nego = $po->details->pluck('no_nego')->unique()->implode(', ');
        $po->no_pr = $po->details->pluck('no_pr')->unique()->implode(', ');
        $po->no_just = $po->details->pluck('no_just')->unique()->implode(', ');
        $po->subtotal = $po->details->sum(function ($detail) {
            return $detail->harga_per_unit * $detail->qty;
        });
        $po->ongkos = 0;
        $po->asuransi = 0;
        $po->total = $po->subtotal + $po->ongkos + $po->asuransi;

        // dd($po);
        $pdf = PDF::loadview('purchase_order.po_print', compact('po'));
        $pdf->setPaper('A4', 'landscape');
        $nama = $po->nama_proyek;
        $no = $po->no_po;
        return $pdf->stream('PO-' . $nama . '(' . $no . ')' . '.pdf');
        // return view('purchase_order.po_print', compact('po'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request  $request)
    {
        $delete_po = $request->id;
        $delete_po = DB::table('purchase_order')->where('id', $delete_po)->delete();

        if ($delete_po) {
            return redirect()->route('purchase_order.index')->with('success', 'Data PO berhasil dihapus');
        } else {
            return redirect()->route('purchase_order.index')->with('error', 'Data PO gagal dihapus');
        }

        return redirect()->route('purchase_order.index');
    }

    // Controller PO/PL

    public function storePOPL(Request $request)
    {
        $purchase_order = $request->id;
        $request->validate(
            [
                'no_po' => 'required',
                // 'vendor_id' => 'nullable',
                'tanggal_po' => 'required',
                'batas_po' => 'required',
                'incoterm' => 'required',
                // 'pr_id' => 'required',
                'term_pay' => 'required',
                'proyek_id' => 'required',
                'ref_po' => 'nullable',

            ],
            [
                'no_po.required' => 'No. PO harus diisi',
                // 'vendor_id.required' => 'Vendor harus diisi',
                'tanggal_po.required' => 'Tanggal PO harus diisi',
                'batas_po.required' => 'Batas Akhir PO harus diisi',
                'incoterm.required' => 'Incoterm harus diisi',
                // 'pr_id.required' => 'PR harus diisi',
                'term_pay.required' => 'Termin Pembayaran harus diisi',
                'proyek_id.required' => 'Proyek harus diisi',
            ]
        );

        if (empty($purchase_order)) {
            $tipe = 1; // 0 = PO biasa, 1 = PO PL
            $po = DB::table('purchase_order')->insertGetId([
                'tipe' => $tipe,
                'no_po' => $request->no_po,
                'tanggal_po' => $request->tanggal_po,
                'batas_po' => $request->batas_po,
                'incoterm' => $request->incoterm,
                'ref_po' => $request->ref_po,
                'term_pay' => $request->term_pay,
                'garansi' => $request->garansi,
                'proyek_id' => $request->proyek_id,
                'pr_id' => $request->pr_id,
                'catatan_vendor' => $request->catatan_vendor
            ]);

            $prs = DetailPR::where('id_pr', $request->pr_id)->get();


            foreach ($prs as $pr) {
                DetailPo::insert([
                    'id_po' => $po,
                    'id_pr' => $request->pr_id,
                    'id_detail_pr' => $pr->id,
                ]);
            }

            return redirect()->route('product.showPOPL')->with('success', 'Data PO berhasil ditambahkan');
        } else {
            DB::table('purchase_order')->where('id', $purchase_order)->update([
                'no_po' => $request->no_po,
                // 'vendor_id' => $request->vendor_id,
                'tanggal_po' => $request->tanggal_po,
                'batas_po' => $request->batas_po,
                'incoterm' => $request->incoterm,
                'pr_id' => $request->pr_id,
                'ref_po' => $request->ref_po,
                'term_pay' => $request->term_pay,
                'garansi' => $request->garansi,
                'proyek_id' => $request->proyek_id,
                'catatan_vendor' => $request->catatan_vendor

            ]);
            return redirect()->route('product.showPOPL')->with('success', 'Data PO berhasil diubah');
        }
    }

    public function destroyPOPL(Request  $request)
    {
        $delete_po = $request->id;
        $delete_po = DB::table('purchase_order')->where('id', $delete_po)->delete();

        if ($delete_po) {
            return redirect()->route('product.showPOPL')->with('success', 'Data PO berhasil dihapus');
        } else {
            return redirect()->route('product.showPOPL')->with('error', 'Data PO gagal dihapus');
        }

        return redirect()->route('product.showPOPL');
    }

    // Hapus Multiple CheckBox
    public function hapusMultiplePo(Request $request)
    {
        if ($request->has('ids')) {
            Purchase_Order::whereIn('id', $request->input('ids'))->delete();
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false]);
        }
    }

    // Hapus Multiple CheckBox
    public function hapusMultiplePo_Pl(Request $request)
    {
        if ($request->has('ids')) {
            Purchase_Order::whereIn('id', $request->input('ids'))->delete();
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false]);
        }
    }

    // Hapus Multiple CheckBox
    public function hapusMultipleTracking(Request $request)
    {
        if ($request->has('ids')) {
            PurchaseRequest::whereIn('id', $request->input('ids'))->delete();
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false]);
        }
    }




    // CONTROLLER KEUANGAN
    public function aprrovedPO(Request  $request)
    {
        $search = $request->q;
        if (Session::has('selected_warehouse_id')) {
            $warehouse_id = Session::get('selected_warehouse_id');
        } else {
            $warehouse_id = DB::table('warehouse')->first()->warehouse_id;
        }

        $purchases = Purchase_Order::select('purchase_order.*', 'vendor.nama as vendor_name', 'keproyekan.nama_proyek as proyek_name', 'purchase_request.no_pr as pr_no')
            ->join('vendor', 'vendor.id', '=', 'purchase_order.vendor_id')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->leftjoin('purchase_request', 'purchase_request.id', '=', 'purchase_order.pr_id')
            ->paginate(50);
        $vendors = DB::table('vendor')->get();
        $proyeks = DB::table('keproyekan')->get();


        if ($search) {
            $purchases = Purchase_Order::where('no_po', 'LIKE', "%$search%")->paginate(50);
        }

        if ($request->format == "json") {
            $purchases = Purchase_Order::where("warehouse_id", $warehouse_id)->get();

            return response()->json($purchases);
        } else {
            $prs = PurchaseRequest::all();
            return view('keuangan.approvedPO', compact('purchases', 'vendors', 'proyeks', 'prs'));
        }
    }

    public function aprrovedPO_PL(Request  $request)
    {
        $search = $request->q;
        if (Session::has('selected_warehouse_id')) {
            $warehouse_id = Session::get('selected_warehouse_id');
        } else {
            $warehouse_id = DB::table('warehouse')->first()->warehouse_id;
        }

        $purchases = Purchase_Order::select('purchase_order.*', 'keproyekan.nama_proyek as proyek_name', 'purchase_request.no_pr as pr_no')
            ->where('purchase_order.tipe', '1')
            ->leftjoin('keproyekan', 'keproyekan.id', '=', 'purchase_order.proyek_id')
            ->leftjoin('purchase_request', 'purchase_request.id', '=', 'purchase_order.pr_id')
            ->paginate(50);
        $vendors = DB::table('vendor')->get();
        $proyeks = DB::table('keproyekan')->get();


        if ($search) {
            $purchases = Purchase_Order::where('no_po', 'LIKE', "%$search%")->paginate(50);
        }

        if ($request->format == "json") {
            $purchases = Purchase_Order::where("warehouse_id", $warehouse_id)->get();

            return response()->json($purchases);
        } else {
            $prs = PurchaseRequest::all();
            return view('keuangan.approvedPOPL', compact('purchases', 'vendors', 'proyeks', 'prs'));
        }
    }
}
