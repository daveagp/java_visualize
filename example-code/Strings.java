public class Strings {
   public static void main(String[] args) {
      // In "options", enable "Show String... objects"
      String a = "Hello, world!";
      String b = "Hello, world!!".substring(0, 13);
      String c = "Hello, ";
      c += "world!";
      String d = "Hello, w"+"orld!";
      String e = a.substring(0, 13);
      System.out.println((a == b) + " " + a.equals(b));
      System.out.println((a == c) + " " + a.equals(c));
      System.out.println((a == d) + " " + a.equals(d));
      System.out.println((a == e) + " " + a.equals(d));
   }
}