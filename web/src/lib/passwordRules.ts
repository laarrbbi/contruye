// Mirrors Password::defaults() in api/app/Providers/AppServiceProvider.php.
// The forms said 8 while the API required 10: a 9-character password passed
// here, then came back refused after the round trip.
export const PASSWORD_MIN_LENGTH = 10
