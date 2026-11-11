const { contextBridge, ipcRenderer } = require('electron');

// Bridges the page to Electron's native printing so a receipt/invoice/
// takings printout goes straight to the configured physical printer instead
// of opening the OS print dialog (the app has no jsPrintSetup extension to
// fall back on — that was a Firefox-only plugin the receipt views already
// supported, this is its Electron-native equivalent).
contextBridge.exposeInMainWorld('beamlinkPrint', {
    // printerRole: 'receipt_printer' | 'invoice_printer' | 'takings_printer'
    printSilently: (printerRole) => ipcRenderer.invoke('print-silently', printerRole),
    listPrinters: () => ipcRenderer.invoke('list-printers'),
    getSelectedPrinter: (printerRole) => ipcRenderer.invoke('get-selected-printer', printerRole),
    resolvePrinter: (printerRole) => ipcRenderer.invoke('resolve-printer', printerRole),
    setSelectedPrinter: (printerRole, deviceName) => ipcRenderer.invoke('set-selected-printer', printerRole, deviceName),
    // Opens the OS Bluetooth pairing screen so a Bluetooth receipt printer
    // can be paired; once paired it shows up in listPrinters() like any
    // other system printer.
    openBluetoothSettings: () => ipcRenderer.invoke('open-bluetooth-settings'),
});
