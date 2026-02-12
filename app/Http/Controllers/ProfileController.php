<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit()
    {
        return Inertia::render('Profile/Edit', [
            'user' => auth()->user()->load('roles'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . auth()->id(),
            'department' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'photo' => 'nullable|image|max:1024', // 1MB Max
        ]);

        $user = auth()->user();
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'department' => $request->department,
            'position' => $request->position,
        ];

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->profile_photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo);
            }

            $path = $request->file('photo')->store('profile-photos', 'public');
            $data['profile_photo'] = $path;
            
            // Mirror to employee table if employee exists
            $employee = \App\Models\Employee::where('user_id', $user->id)->first();
            if ($employee) {
                $employee->update(['profile_photo' => $path]);
            }
        }

        $user->update($data);

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, auth()->user()->password)) {
            return redirect()->back()->with('error', 'Current password is incorrect.');
        }

        auth()->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Password updated successfully.');
    }
}