import * as XLSX from "xlsx";

export function exportToExcel(headerNames: string[], dataKeys: string[], allData: any[], fileName: string): void {
    const exportData: any[][] = [];
    exportData.push(headerNames);
    allData.forEach(item => {
        const row = dataKeys.map(key => item[key]);
        exportData.push(row);
    });
    const worksheet = XLSX.utils.aoa_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Sheet1');
    XLSX.writeFile(workbook, fileName + '.xlsx');
}
