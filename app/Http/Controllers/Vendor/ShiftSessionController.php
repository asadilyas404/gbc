<?php

namespace App\Http\Controllers\Vendor;

use App\Models\User;
use Google\Rpc\Help;
use App\Models\ShiftSession;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\VendorEmployee;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;

class ShiftSessionController extends Controller
{
    public function index()
    {
        $currentSession = ShiftSession::current();
            
        
        // Check if the user is vendor or vendor-employee
        if(auth('vendor')->check()){
            $userId = auth('vendor')->id();
            $currentSession = $currentSession->where('user_id', $userId);
            $currentSession->with('restaurant');
        } 
        if (auth('vendor_employee')->check()) {
            $userId = auth('vendor_employee')->id();
            $currentSession = $currentSession->where('user_id', $userId);
            $currentSession->with('user');
        }

        $currentSession = $currentSession->first();
        // Get available shifts from tbl_defi_shift
        $shifts = DB::table('tbl_defi_shift')
                    ->select('shift_id', 'shift_name')
                    ->orderBy('shift_name')
                    ->get();

        $users = VendorEmployee::where('restaurant_id', Helpers::get_restaurant_id())
        ->get();

        return view('vendor-views.shift-session.index', compact('currentSession', 'shifts','users'));
    }

    public function store(Request $request)
    {
        // Check if there's already an open session
        $existingSession = ShiftSession::current()
        ->where('user_id', auth('vendor')->id() ?? auth('vendor_employee')->id())
        ->first();
        if ($existingSession) {
            Toastr::warning('A shift session is already open. Please close the current session before starting a new one.');
            return back();
        }

        $request->validate([
            'shift_id' => 'required|integer|exists:tbl_defi_shift,shift_id',
            'opening_cash' => 'required|numeric|min:0',
            'opening_visa' => 'required|numeric|min:0',
        ], [
            'shift_id.required' => 'Please select a shift.',
            'shift_id.exists' => 'Selected shift is invalid.',
            'opening_cash.required' => 'Opening cash amount is required.',
            'opening_cash.min' => 'Opening cash amount must be at least 0.',
            'opening_visa.required' => 'Opening visa amount is required.',
            'opening_visa.min' => 'Opening visa amount must be at least 0.',
        ]);

        try {
            $shiftSession = new ShiftSession();
            $shiftSession->shift_id = $request->shift_id;
            $shiftSession->start_date = now();
            $shiftSession->opening_cash = $request->opening_cash;
            $shiftSession->opening_visa = $request->opening_visa;
            $shiftSession->branch_id = Helpers::get_restaurant_id();
            $shiftSession->save();

            Toastr::success('Shift session started successfully!');
            return back();
        } catch (\Exception $e) {
            Toastr::error('Failed to start shift session. Please try again.');
            return back();
        }
    }

    public function close(Request $request)
    {
        $currentSession = ShiftSession::current()
        ->where('user_id', auth('vendor')->id() ?? auth('vendor_employee')->id())
        ->first();

        if (!$currentSession) {
            Toastr::warning('No open shift session found.');
            return back();
        }

        $request->validate([
            'closing_cash' => 'required|numeric|min:0',
            'closing_visa' => 'required|numeric|min:0',
        ], [
            'closing_cash.required' => 'Closing cash amount is required.',
            'closing_cash.min' => 'Closing cash amount must be at least 0.',
            'closing_visa.required' => 'Closing visa amount is required.',
            'closing_visa.min' => 'Closing visa amount must be at least 0.',
        ]);

        try {
            $closingData = [
                'closing_cash' => $request->closing_cash,
                'closing_visa' => $request->closing_visa,
            ];

            $currentSession->closeSession($closingData);

            Toastr::success('Shift session closed successfully!');
            return back();
        } catch (\Exception $e) {
            Toastr::error('Failed to close shift session. Please try again.');
            return back();
        }
    }

    public function getShiftDetails($shiftId)
    {
        $shift = DB::table('tbl_defi_shift')
                   ->where('shift_id', $shiftId)
                   ->first();

        if (!$shift) {
            return response()->json(['error' => 'Shift not found'], 404);
        }

        return response()->json([
            'shift_id' => $shift->shift_id,
            'shift_name' => $shift->shift_name
        ]);
    }

    public function getCurrentSession()
    {
        $currentSession = ShiftSession::current()->first();

        if (!$currentSession) {
            return response()->json(['session' => null]);
        }

        return response()->json([
            'session' => [
                'session_id' => $currentSession->session_id,
                'shift_name' => $currentSession->shift_name,
                'start_date' => $currentSession->start_date->format('Y-m-d H:i:s'),
                'opening_cash' => $currentSession->opening_cash,
                'opening_visa' => $currentSession->opening_visa,
                'session_no' => $currentSession->session_no,
                'session_status' => $currentSession->session_status,
            ]
        ]);
    }
}
