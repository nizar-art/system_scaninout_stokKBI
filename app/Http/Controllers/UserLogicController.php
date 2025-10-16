<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserLogicController extends Controller
{
    //
    public function index()
    {
        // Urutan role custom: SuperAdmin, admin, User, view
        $roleOrder = ['SuperAdmin', 'admin', 'User', 'view'];
        $users = User::with('roles')->get();
        $users = $users->sort(function ($a, $b) use ($roleOrder) {
            $aRoles = $a->roles->pluck('name')->toArray();
            $bRoles = $b->roles->pluck('name')->toArray();
            $aPos = min(array_map(fn($r) => array_search($r, $roleOrder) === false ? count($roleOrder) : array_search($r, $roleOrder), $aRoles) ?: [count($roleOrder)]);
            $bPos = min(array_map(fn($r) => array_search($r, $roleOrder) === false ? count($roleOrder) : array_search($r, $roleOrder), $bRoles) ?: [count($roleOrder)]);
            if ($aPos === $bPos) {
                return $b->created_at <=> $a->created_at;
            }
            return $aPos <=> $bPos;
        });
        return view('Users.index', ['users' => $users]);
    }

    // Menampilkan form untuk menambah pengguna baru
    public function create()
    {
        $roles = Role::all();
        return view('Users.create', compact('roles'));
    }

    // Menyimpan pengguna baru ke dalam database
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:tbl_user',
            // 'email' => 'nullable|email|unique:tbl_user,email',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'ID-card' => 'required|string|min:3|unique:tbl_user,nik',
            'role' => 'required|array',
            'role.*' => 'exists:tbl_role,name',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rawPassword = $request->password ?: $request->input('ID-card');
        $user = User::create([
            'username' => $request->username,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            // 'email' => $request->email,
            'nik' => $request->input('ID-card'),
            'password' => Hash::make($rawPassword),
        ]);

        $roleIds = Role::whereIn('name', $request->role)->pluck('id');
        $user->roles()->attach($roleIds);

        return redirect()->route('users.index')->with('success', 'User successfully created');
    }


    // Menampilkan form untuk mengedit pengguna
    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = Role::all();
        return view('Users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:tbl_user,username,' . $id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            // 'email' => 'nullable|email|unique:tbl_user,email,' . $id,
            'ID-card' => 'required|string|max:50',
            'role' => 'nullable|array',
            'role.*' => 'exists:tbl_role,name',
            'password' => [
                'nullable',
                'string',
                'min:4',
            ]
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $user = User::findOrFail($id);
        $user->username = $request->username;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        // $user->email = $request->email;
        $user->nik = $request->{'ID-card'};
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();
        // Ambil role yang sudah dimiliki user
        $existingRoleIds = $user->roles->pluck('id')->toArray();
        $newRoleIds = $request->role ? Role::whereIn('name', $request->role)->pluck('id')->toArray() : [];
        // Cari role yang belum dimiliki user
        $rolesToAttach = array_diff($newRoleIds, $existingRoleIds);
        if (!empty($rolesToAttach)) {
            $user->roles()->attach($rolesToAttach);
        }
        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }


    // Menghapus pengguna dari database
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->roles()->detach();
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User successfully deleted');
    }

}