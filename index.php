<?php

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $resp = [];

    $_POST = json_decode(file_get_contents('php://input'), true);

    if (!isset($_POST['url']) || empty($_POST['url'])) {
        http_response_code(400);
        echo "Invalid Request, Something is empty !";
        exit;
    }

    $url = $_POST['url'];
    $maxRedirect = 5;
    $timeout = 25; //sec

    //if not url
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo "Invalid URL, Something is empty !";
        exit;
    }

    $redirect = false;
    $location = [];
    $httpcodes = [];
    $nowUrl = $url;
    $err = "";

    foreach (range(1, $maxRedirect) as $i) {

        $ch = curl_init($nowUrl);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //timeout in seconds
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpcodes[] = $httpcode;

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            break;
        }

        curl_close($ch);

        if ($httpcode === 301 || $httpcode === 302) {

            $redirect = true;
            $redirectUrl = "";

            if (preg_match('~Location: (.*)~i', $output, $match)) {
                $location[] = trim($match[1]);
                $redirectUrl = trim($match[1]);
            }

            $nowUrl = $redirectUrl;
        } else {
            break;
        }
    }


    $resp = [
        "url" => $url,
        "statuses" => $httpcodes,
        "redirect" => $redirect,
        "redirectUrls" => $location,
        "error" => $err,
    ];


    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resp);
    exit;
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTTP BULK STATUS CHECKER</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/axios/0.27.2/axios.min.js" integrity="sha512-odNmoc1XJy5x1TMVMdC7EMs3IVdItLPlCeL5vSUPN2llYKMJ2eByTTAIiiuqLg+GdNr9hF6z81p27DArRFKT7A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <style>
        .status {
            padding: 5px 7px;
            color: white;
            border-radius: 3px;
            display: inline;
            margin: 0px 4px;
        }

        .redirect-status {
            background-color: #0277BD;
        }

        .error-status {
            background-color: #D50000;
        }

        .ok-status {
            background-color: #43A047;
        }
    </style>
</head>

<body>

    <section class="mx-3 my-3">

        <h2 class="text-center mb-4">HTTP STATUS CODE CHECKER</h2>
        <div class="form-floating">
            <textarea style="height : 200px;" class="form-control" placeholder="One URL per line" id="urls"></textarea>
            <label for="urls">URL (One URL per line)</label>
        </div>
        <button onclick="checkStatus()" id="checkBtn" type="button" class="btn btn-primary my-2">Check</button>


        <p class="text-danger my-2" id="errorMsg"></p>

        <div class="status-table mt-4">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">URL</th>
                        <th scope="col">Status Codes</th>
                        <th scope="col">Redirect</th>
                        <th scope="col">Redirect URLs</th>
                    </tr>
                </thead>
                <tbody id="tableBody">

                </tbody>
            </table>
        </div>

    </section>

</body>

<script>
    setInterval(() => {

        localStorage.setItem('urlstorage', urls.value);

    }, 3000);


    urls.value = localStorage.getItem("urlstorage") || "";

    const checkStatus = async () => {

        tableBody.innerHTML = "";

        if (!urls.value) {
            return;
        }

        checkBtn.disabled = true;
        checkBtn.innerHTML = "Checking....";


        const urlsValue = urls.value.split("\n");


        for (const [index, u] of urlsValue.entries()) {

            if (u === "") {
                continue;
            }


            try {

                const api = await axios.post("", {
                    url: u
                });

                let statuses = "";
                let apiData = api.data;

                if (apiData.error === "") {

                    for (let s of apiData.statuses) {

                        let sClass = "";
                        s = s.toString();

                        if (s[0] == "3") {
                            sClass = "redirect-status";
                        } else if (s[0] == "2") {
                            sClass = "ok-status";
                        } else if (s[0] == "4" || s[0] == "5") {
                            sClass = "error-status";
                        }

                        statuses += `<div class="status ${sClass}">${s}</div>`;
                    }
                }
                else{

                    statuses += `<div class="status error-status">ERROR</div>`;

                }

                tableBody.innerHTML += `
                <tr>
                    <th scope="row">${index + 1}</th>
                    <td>${apiData.url}</td>
                    <td>${statuses}</td>
                    <td>${apiData.redirect}</td>
                    <td>${apiData.redirectUrls.toString()}</td>
                </tr>`;



            } catch (err) {


                errorMsg.innerHTML = err.response.data;
                checkBtn.disabled = false;
                checkBtn.innerHTML = "Check";

            }

        }

        checkBtn.disabled = false;
        checkBtn.innerHTML = "Check";


    }
</script>

</html>