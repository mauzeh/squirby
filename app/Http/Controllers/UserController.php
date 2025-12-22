<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Services\UserSeederService;
use App\Services\UserFormService;
use App\Services\ComponentBuilder as C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    protected UserFormService $userFormService;

    public function __construct(UserFormService $userFormService)
    {
        $this->userFormService = $userFormService;
    }
    public function index()
    {
        $users = User::with('roles')->get();
        
        $components = [];

        // Title
        $components[] = C::title('User Administration')
            ->subtitle('Manage user accounts and permissions')
            ->build();

        // Add User button
        $components[] = C::button('Add User')
            ->asLink(route('users.create'))
            ->build();

        // Table of users
        if ($users->isNotEmpty()) {
            $tableBuilder = C::table();

            foreach ($users as $user) {
                $line1 = $user->name;
                $line2 = $user->email;
                $line3 = $user->roles->pluck('name')->join(', ');

                $rowBuilder = $tableBuilder->row(
                    $user->id,
                    $line1,
                    $line2,
                    $line3
                );
                
                // Add edit action (pencil icon)
                $rowBuilder->linkAction(
                    'fa-solid fa-pencil',
                    route('users.edit', $user->id),
                    'Edit user',
                    'btn-transparent'
                );
                
                // Add impersonate action
                $rowBuilder->linkAction(
                    'fa-solid fa-user-secret',
                    route('users.impersonate', $user->id),
                    'Impersonate user'
                );
                
                $rowBuilder->wrapText()->compact()->add();
            }

            $components[] = $tableBuilder->build();
        } else {
            // Empty state
            $components[] = C::messages()
                ->info('No users yet.')
                ->build();
        }

        $data = ['components' => $components];
        return view('mobile-entry.flexible', compact('data'));
    }

    public function create()
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request, UserSeederService $userSeederService)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Seed the new user with default data
        $userSeederService->seedNewUser($user);

        $user->roles()->sync($request->input('roles'));

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        
        $components = [
            C::title('Edit User')
                ->subtitle('Manage user account and permissions')
                ->backButton('fa-arrow-left', route('users.index'), 'Back to users')
                ->build(),
        ];

        // Add session messages if any
        $sessionMessages = C::messagesFromSession();
        if ($sessionMessages) {
            $components[] = $sessionMessages;
        }

        // Add form components
        $components[] = $this->userFormService->generateUserInformationForm($user, $roles);
        $components[] = $this->userFormService->generatePasswordForm($user);
        $components[] = $this->userFormService->generateDeleteUserForm($user);

        return view('mobile-entry.flexible', [
            'data' => [
                'components' => $components,
            ]
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'nullable|array',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $user->roles()->sync($request->input('roles'));

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function impersonate(User $user)
    {
        session(['impersonator_id' => auth()->id()]);
        auth()->login($user);
        return redirect('/')->with('success', 'Impersonating user.');
    }

    public function leaveImpersonate()
    {
        auth()->loginUsingId(session('impersonator_id'));
        session()->forget('impersonator_id');
        return redirect()->route('users.index')->with('success', 'Stopped impersonating.');
    }
}
