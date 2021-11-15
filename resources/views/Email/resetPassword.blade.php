@component('mail::message')
# Introduction

Please click this button to reset Password.

@component('mail::button', ['url' => 'password/reset/'.$token])
Button Text
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
