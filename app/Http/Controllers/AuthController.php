<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User; // ✅ Laravel 10 uses Models namespace
use App\Models\BillInfoFetchPayment; // aravel 10 uses Models namespace
use App\Models\ComplaintRegisterTrack;
use Illuminate\Support\Facades\Mail;

// aravel 10 uses Models namespace

class AuthController extends Controller
{

    // Handle login
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            $user =Auth::user();
            
            // if($user->role_id == 1){
            //       return redirect()->route('services.dashboard')->with('success', 'Logged in successfully');
            // }else{
            // return redirect('dashboard')->with('success', 'Logged in successfully');
                
            // }
             return redirect('dashboard')->with('success', 'Logged in successfully');

        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ]);
    }

    // Handle register
    public function register1(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => 2,
        ]);

        Auth::login($user);

        return redirect()->intended('dashboard')->with('success', 'Account created');
    }

    // Handle logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Logged out');
    }

    // ---- Services categories ----
    public function toggleDarkMode(Request $request)
    {
        session(['dark_mode' => $request->dark_mode]);
        return response()->json(['success' => true]);
    }
    
    
    public function updatePasswordLogic(Request $request)
    {
          $request->validate([
            'current_password' => ['required'],
            'new_password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
                'different:current_password', // must be different from current
                'regex:/[@$!%*?&]/' // must include at least one special character
            ],
        ], [
            'new_password.different' => 'New password must be different from the current password.',
            'new_password.regex' => 'New password must include at least one special character (@$!%*?&).',
        ]);
    
        $user = Auth::user();
    
        // Check if current password matches
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }
    
        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
    
        return back()->with('success', 'Password changed successfully!');
    }
    
    public function contactSend(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'attachment' => 'nullable|file|mimes:png|max:2048',
        ]);

        // prepare email data
        $data = [
            'subject' => $validated['subject'],
            'description' => $validated['description'],
        ];

        // send mail
        Mail::send('payments.emailsend', $data, function ($message) use ($request) {
            $message->to('intern@spay.live')
                    ->subject('New Contact Message: '.$request->subject);

            if ($request->hasFile('attachment')) {
                $message->attach($request->file('attachment')->getRealPath(), [
                    'as' => $request->file('attachment')->getClientOriginalName(),
                    'mime' => $request->file('attachment')->getMimeType(),
                ]);
            }
        });

        return back()->with('success', 'Your message has been sent successfully!');
    }
    
    // public function updateImage(Request $request)
    // {
    //     $user = auth()->user();
    
    //     // If base64 image (from camera/upload)
    //     if (strpos($request->image, 'base64') !== false) {
    //         $imageData = explode(',', $request->image)[1];
    //         $imageName = 'profile_' . time() . '.png';
    //         $path = public_path('uploads/profile/' . $imageName);
    
    //         file_put_contents($path, base64_decode($imageData));
    //         $user->profile_image = 'uploads/profile/' . $imageName;
    //     } else {
    //         // If selected avatar (URL)
    //         $user->profile_image = $request->image;
    //     }
    
    //     $user->save();
    
    //     return response()->json(['status' => 'success', 'path' => $user->profile_image]);
    // }
    
    public function updateImage(Request $request)
    {
        $user = auth()->user();
        $image = $request->image;
    
        if (strpos($image, 'base64') !== false) {
            // Handle Base64 Image
            $imageParts = explode(";base64,", $image);
            $imageBase64 = base64_decode($imageParts[1]);
    
            $imageName = 'profile_' . time() . '.png';
            $path = 'uploads/profile/' . $imageName;
    
            // Ensure directory exists
            if (!file_exists(public_path('uploads/profile'))) {
                mkdir(public_path('uploads/profile'), 0777, true);
            }
    
            file_put_contents(public_path($path), $imageBase64);
    
            $user->profile_image = $path;
        } else {
            // Handle Avatar (direct URL)
            $user->profile_image = $image;
        }
    
        $user->save();
    
        return response()->json([
            'status' => 'success',
            'path'   => asset($user->profile_image)
        ]);
    }


    
    
    
    
    
}
