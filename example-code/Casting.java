public class Casting {
   public static void main(String[] args) {
      // casting doesn't change the object
      Object obj;
      { 
          String arr = "some text";
          obj = arr;
      }
      System.out.println(arr); // still a string
   }
}
        