using System.Threading.Tasks;
public static class NativeConfigurationCall {
 public static Task<NativeSipSmokeResult> Start(int port,string userA,string secretA,string userB,string secretB) {
  return Task.Factory.StartNew(()=>NativeSipSmoke.Run(port,userA,secretA,userB,secretB));
 }
}
