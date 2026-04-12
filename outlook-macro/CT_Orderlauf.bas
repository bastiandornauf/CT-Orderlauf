Attribute VB_Name = "CT_Orderlauf"
' ============================================================
' CT-Orderlauf – Outlook-Makro
' ============================================================
' Voraussetzung:
'   1. "ct-orderlauf-export.xml" und alle PDFs liegen im
'      Downloads-Ordner (oder im Pfad EXPORT_FOLDER unten).
'   2. Verweis auf "Microsoft XML, v6.0" gesetzt:
'      Extras > Verweise > Microsoft XML, v6.0
'
' Einmalige Einrichtung:
'   - Diese Datei in Outlook importieren:
'     Alt+F11 > Datei > Datei importieren > CT_Orderlauf.bas
'   - Makro per Tastenkürzel belegen:
'     Datei > Optionen > Menüband anpassen > Tastenkombinationen
'     Kategorie "Makros" > CT_Orderlauf.ImportBestellungen
' ============================================================

' Pfad zum Export-Ordner (Standard: Downloads des aktuellen Benutzers)
Private Const EXPORT_FOLDER As String = ""  ' Leer = automatisch Downloads-Ordner

' Auf True setzen, um Mails direkt zu senden statt nur als Entwurf anzuzeigen
Private Const AUTO_SEND As Boolean = False

' ============================================================

Sub ImportBestellungen()
    Dim sFolder As String
    Dim sXmlPath As String

    ' Ordner bestimmen
    If EXPORT_FOLDER = "" Then
        sFolder = Environ("USERPROFILE") & "\Downloads\"
    Else
        sFolder = EXPORT_FOLDER
        If Right(sFolder, 1) <> "\" Then sFolder = sFolder & "\"
    End If

    sXmlPath = sFolder & "ct-orderlauf-export.xml"

    ' Prüfen ob XML vorhanden
    If Not DateiExistiert(sXmlPath) Then
        MsgBox "Export-Datei nicht gefunden:" & vbCrLf & sXmlPath & vbCrLf & vbCrLf & _
               "Bitte zuerst in CT-Orderlauf auf 'Für Outlook exportieren' klicken.", _
               vbExclamation, "CT-Orderlauf"
        Exit Sub
    End If

    ' XML laden
    Dim xml As New MSXML2.DOMDocument60
    xml.async = False
    If Not xml.Load(sXmlPath) Then
        MsgBox "XML konnte nicht geladen werden: " & xml.parseError.reason, _
               vbCritical, "CT-Orderlauf"
        Exit Sub
    End If

    Dim orders As MSXML2.IXMLDOMNodeList
    Set orders = xml.SelectNodes("//order")

    If orders.Length = 0 Then
        MsgBox "Keine Bestellungen in der Export-Datei gefunden.", vbInformation, "CT-Orderlauf"
        Exit Sub
    End If

    Dim nErstellt As Integer
    Dim nOhneAnhang As Integer
    nErstellt = 0
    nOhneAnhang = 0

    Dim i As Integer
    For i = 0 To orders.Length - 1
        Dim order As MSXML2.IXMLDOMNode
        Set order = orders(i)

        Dim sTo       As String
        Dim sCc       As String
        Dim sSubject  As String
        Dim sBody     As String
        Dim sPdfName  As String
        Dim sType     As String

        sTo      = KnotenText(order, "to")
        sCc      = KnotenText(order, "cc")
        sSubject = KnotenText(order, "subject")
        sBody    = KnotenText(order, "body")
        sPdfName = KnotenText(order, "pdf")
        sType    = KnotenText(order, "order_type")

        ' Webshop-Lieferanten werden nicht per Mail verschickt
        If sType = "webshop" Then
            GoTo NaechsteBestellung
        End If

        If sTo = "" Then
            GoTo NaechsteBestellung
        End If

        ' Mail erstellen
        Dim mail As Outlook.MailItem
        Set mail = Application.CreateItem(olMailItem)

        With mail
            .To = sTo
            If sCc <> "" Then .CC = sCc
            .Subject = sSubject
            .Body = sBody

            ' PDF anhängen
            Dim sPdfPath As String
            sPdfPath = sFolder & sPdfName
            If DateiExistiert(sPdfPath) Then
                .Attachments.Add sPdfPath
            Else
                nOhneAnhang = nOhneAnhang + 1
            End If

            If AUTO_SEND Then
                .Send
            Else
                .Display
            End If
        End With

        nErstellt = nErstellt + 1

NaechsteBestellung:
    Next i

    ' Abschlussmeldung
    Dim sMsg As String
    sMsg = nErstellt & " E-Mail(s) wurden vorbereitet"
    If AUTO_SEND Then
        sMsg = sMsg & " und gesendet."
    Else
        sMsg = sMsg & " und als Entwurf geöffnet."
    End If
    If nOhneAnhang > 0 Then
        sMsg = sMsg & vbCrLf & vbCrLf & _
               "Hinweis: Bei " & nOhneAnhang & " Mail(s) wurde kein PDF gefunden." & vbCrLf & _
               "Bitte PDFs im Downloads-Ordner prüfen (" & sFolder & ")."
    End If

    MsgBox sMsg, vbInformation, "CT-Orderlauf"
End Sub

' ============================================================
' Hilfsfunktionen
' ============================================================

Private Function KnotenText(node As MSXML2.IXMLDOMNode, tagName As String) As String
    Dim child As MSXML2.IXMLDOMNode
    Set child = node.SelectSingleNode(tagName)
    If child Is Nothing Then
        KnotenText = ""
    Else
        KnotenText = child.Text
    End If
End Function

Private Function DateiExistiert(pfad As String) As Boolean
    DateiExistiert = (Dir(pfad) <> "")
End Function
