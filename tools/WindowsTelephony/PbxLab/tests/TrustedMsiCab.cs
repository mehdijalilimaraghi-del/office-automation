using System;
using System.IO;
using System.Runtime.InteropServices;
public static class TrustedMsiCab {
 [DllImport("msi.dll",CharSet=CharSet.Unicode)] static extern uint MsiOpenDatabase(string path,IntPtr mode,out uint db);
 [DllImport("msi.dll",CharSet=CharSet.Unicode)] static extern uint MsiDatabaseOpenView(uint db,string sql,out uint view);
 [DllImport("msi.dll")] static extern uint MsiViewExecute(uint view,uint record);
 [DllImport("msi.dll")] static extern uint MsiViewFetch(uint view,out uint record);
 [DllImport("msi.dll")] static extern uint MsiRecordReadStream(uint record,uint field,byte[] data,ref uint size);
 [DllImport("msi.dll")] static extern uint MsiCloseHandle(uint handle);
 static void Check(uint value){if(value!=0)throw new IOException("MSI read error "+value);}
 public static void Save(string source,string target){uint db=0,view=0,record=0;try{
  Check(MsiOpenDatabase(source,IntPtr.Zero,out db));Check(MsiDatabaseOpenView(db,"SELECT `Data` FROM `_Streams` WHERE `Name`='media1.cab'",out view));Check(MsiViewExecute(view,0));Check(MsiViewFetch(view,out record));
  using(var output=new FileStream(target,FileMode.CreateNew,FileAccess.Write)){var data=new byte[65536];while(true){uint size=(uint)data.Length;Check(MsiRecordReadStream(record,1,data,ref size));if(size==0)break;output.Write(data,0,(int)size);}}
 }finally{if(record!=0)MsiCloseHandle(record);if(view!=0)MsiCloseHandle(view);if(db!=0)MsiCloseHandle(db);}}
}