<x-mail::message>
## {{__('EXPORT READY')}}

{{__('Your export file has been successfully generated and is ready for download. Below are the details of your export:')}}

## {{__('File Details:')}}

- {{__('Export Type:')}} {{$exportType}}
@if($formattedDate !== '')
- {{__('Period:')}} {{ $formattedDate }}
@endif
- {{__('File Name:')}} {{$fileName}}

{{__('You can download your file by clicking the button below:')}}

<x-mail::button :url="$downloadUrl" color="success">
{{__('Download Export')}}
</x-mail::button>
</x-mail::message>
