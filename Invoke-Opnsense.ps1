param
(
    [Parameter(Mandatory, Position = 0)]
    [uri]$Uri,

    [Microsoft.PowerShell.Commands.WebRequestMethod]$Method = "Get"
)

if (-not $Uri.IsAbsoluteUri)
{
    $Uri = "https://opnshut.dvlp.casa/$($Uri -replace "^/")"
}

$Config = gc ~/.opn-cli/conf.opnshut.yaml | ConvertFrom-Yaml
$Cred = $Config.api_key, $Config.api_secret -join ":"
$EncodedCred = [System.Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes($Cred))

$Params = @{
    SkipCertificateCheck = $true
    Uri = $Uri
    Method = $Method
    Headers = @{
        Authorization = "Basic $EncodedCred"
    }
}

irm @Params
